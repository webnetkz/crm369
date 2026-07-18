<?php

use App\Models\ApiAccessToken;
use App\Models\EquipmentItem;
use App\Models\PortalWebhook;
use App\Models\TsdQrScan;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\WarehouseItem;
use App\Models\WarehousePlace;
use App\Support\WarehouseHierarchyManager;
use Inertia\Testing\AssertableInertia as Assert;

function tsdApiAdministratorsGroup(): UserGroup
{
    return UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);
}

function tsdApiHeadersFor(string $token): array
{
    return [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ];
}

test('authenticated users can open tsd module and save a qr scan', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tsd.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tsd/Index')
            ->where('autoStartScanner', false)
            ->where('initialQrCode', '')
            ->where('stats.total', 0)
            ->where('recentScans', [])
        );

    $this->actingAs($user)
        ->post(route('tsd.store'), [
            'qr_code' => 'ORD-2026-000145 | cell-a3 | lot-08',
            'device_name' => 'TSD-01',
            'location' => 'Main warehouse',
            'context' => 'acceptance',
        ])
        ->assertRedirect();

    $scan = TsdQrScan::query()->first();

    $this->assertModelExists($scan);

    expect($scan->qr_code)->toBe('ORD-2026-000145 | cell-a3 | lot-08')
        ->and($scan->normalized_qr_code)->toBe('ORD-2026-000145|CELL-A3|LOT-08')
        ->and($scan->source)->toBe(TsdQrScan::SOURCE_WEB)
        ->and($scan->scanned_by_user_id)->toBe($user->id);
});

test('authenticated users can open the qr menu entry with scanner autostart enabled', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('qr.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tsd/Index')
            ->where('autoStartScanner', true)
            ->where('initialQrCode', '')
        );
});

test('native mobile qr scanners can prefill the quick scan form', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('qr.index', ['qr_code' => 'EQ-NATIVE-001']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tsd/Index')
            ->where('autoStartScanner', true)
            ->where('initialQrCode', 'EQ-NATIVE-001')
        );
});

test('authenticated users are redirected to equipment after scanning an equipment qr code', function () {
    $user = User::factory()->create();
    $equipmentItem = EquipmentItem::factory()->create([
        'name' => 'Handheld Scanner',
        'qr_code' => 'EQ-HANDHELD-001',
    ]);

    $this->actingAs($user)
        ->post(route('tsd.store'), [
            'qr_code' => ' EQ-HANDHELD-001 ',
            'device_name' => 'TSD-01',
        ])
        ->assertRedirect(route('equipment.index', ['equipment' => $equipmentItem->id]));

    $scan = TsdQrScan::query()->latest('id')->first();

    $this->assertModelExists($scan);

    expect($scan->qr_code)->toBe('EQ-HANDHELD-001')
        ->and($scan->normalized_qr_code)->toBe('EQ-HANDHELD-001');
});

test('tsd is wired into the sidebar and built in menu items', function () {
    $user = User::factory()->create();

    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));
    $response = $this->actingAs($user)
        ->get(route('settings.menu.edit'))
        ->assertSuccessful();

    $builtInKeys = collect($response->inertiaProps('builtInItems'))->pluck('key');

    expect($sidebar)->toContain("key: 'qr'")
        ->and($sidebar)->toContain('title: t.value.tsd.quick_scan_title')
        ->and($sidebar)->toContain('href: qrIndex()')
        ->and($sidebar)->toContain("isMenuItemVisible('tsd')")
        ->and($sidebar)->toContain('title: t.value.tsd.title')
        ->and($sidebar)->toContain('href: tsdIndex()')
        ->and($builtInKeys->all())->toContain('qr')
        ->and($builtInKeys->all())->toContain('tsd');
});

test('tsd page includes browser camera qr scanning controls', function () {
    $page = file_get_contents(resource_path('js/pages/tsd/Index.vue'));

    expect($page)->toContain('startScanner')
        ->and($page)->toContain('stopScanner')
        ->and($page)->toContain('BarcodeDetector')
        ->and($page)->toContain('ref="videoElement"')
        ->and($page)->toContain('scanner_title')
        ->and($page)->toContain('autoStartScanner')
        ->and($page)->toContain('shouldRenderScannerOnly')
        ->and($page)->toContain('v-if="shouldRenderScannerOnly"');
});

test('api settings include tsd documentation and permissions', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => tsdApiAdministratorsGroup()->id,
    ]);

    $this->actingAs($admin)
        ->get(route('settings.api.documentation.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ApiDocumentation')
            ->where('documentation', fn ($documentation): bool => collect($documentation)->contains(
                fn (array $section): bool => $section['title'] === __('ui.api.section_tsd')
                    && collect($section['endpoints'])->contains(
                        fn (array $endpoint): bool => $endpoint['path'] === '/api/v1/tsd/scans'
                            && $endpoint['permission'] === ApiAccessToken::PERMISSION_TSD_WRITE
                    )
            ))
        );

    $this->actingAs($admin)
        ->get(route('settings.api.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Api')
            ->where('permissions', fn ($permissions): bool => collect($permissions)->contains(
                fn (array $permission): bool => $permission['key'] === ApiAccessToken::PERMISSION_TSD_READ
            ) && collect($permissions)->contains(
                fn (array $permission): bool => $permission['key'] === ApiAccessToken::PERMISSION_TSD_WRITE
            ))
        );
});

test('api tokens can list and create tsd scans', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => tsdApiAdministratorsGroup()->id,
    ]);

    $plainTextToken = ApiAccessToken::generatePlainTextToken();

    ApiAccessToken::query()->create([
        'user_id' => $user->id,
        'name' => 'TSD integration',
        ...ApiAccessToken::tokenAttributes($plainTextToken),
        'permissions' => [
            ApiAccessToken::PERMISSION_TSD_READ,
            ApiAccessToken::PERMISSION_TSD_WRITE,
        ],
    ]);

    $existingScan = TsdQrScan::factory()->create([
        'source' => TsdQrScan::SOURCE_API,
        'scanned_by_user_id' => $user->id,
    ]);

    $this->withHeaders(tsdApiHeadersFor($plainTextToken))
        ->getJson(route('api.v1.tsd.index'))
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $existingScan->id)
        ->assertJsonPath('data.0.source', TsdQrScan::SOURCE_API);

    $this->withHeaders(tsdApiHeadersFor($plainTextToken))
        ->postJson(route('api.v1.tsd.store'), [
            'qr_code' => 'PACK-2026-0007',
            'device_name' => 'API-TSD',
            'location' => 'Packing zone',
            'context' => 'shipping',
        ])
        ->assertCreated()
        ->assertJsonPath('data.qr_code', 'PACK-2026-0007')
        ->assertJsonPath('data.source', TsdQrScan::SOURCE_API);

    expect(TsdQrScan::query()->where('qr_code', 'PACK-2026-0007')->exists())->toBeTrue();
});

test('api tsd scans return resolved warehouse position when qr belongs to a warehouse item', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => tsdApiAdministratorsGroup()->id,
    ]);

    app(WarehouseHierarchyManager::class)->create(
        warehouseHierarchyPayload('Склад API'),
        $user,
    );

    $place = WarehousePlace::query()->where('name', 'A-01-1-001')->firstOrFail();
    $warehouseItem = WarehouseItem::factory()->forPlace($place)->create([
        'name' => 'Серверный блок',
        'sku' => 'SRV-001',
        'qr_code' => 'WI-SRV-001',
        'quantity' => 1,
    ]);

    $plainTextToken = ApiAccessToken::generatePlainTextToken();

    ApiAccessToken::query()->create([
        'user_id' => $user->id,
        'name' => 'TSD resolve',
        ...ApiAccessToken::tokenAttributes($plainTextToken),
        'permissions' => [
            ApiAccessToken::PERMISSION_TSD_WRITE,
        ],
    ]);

    $this->withHeaders(tsdApiHeadersFor($plainTextToken))
        ->postJson(route('api.v1.tsd.store'), [
            'qr_code' => $warehouseItem->qr_code,
        ])
        ->assertCreated()
        ->assertJsonPath('resolved.entity_type', 'item')
        ->assertJsonPath('resolved.title', 'Серверный блок')
        ->assertJsonPath('resolved.location.path', 'Склад API / Ряд A / Колонка 01 / Этаж 1 / A-01-1-001');
});

test('webhook settings include tsd documentation and permissions', function () {
    $admin = User::factory()->create([
        'user_group_id' => tsdApiAdministratorsGroup()->id,
    ]);

    $this->actingAs($admin)
        ->get(route('settings.webhooks.documentation.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/WebhookDocumentation')
            ->where('documentation.tsd_index_url', url('/portal-webhooks').'/{webhook_id}/tsd/scans')
            ->where('documentation.tsd_store_url', url('/portal-webhooks').'/{webhook_id}/tsd/scans')
        );

    $this->actingAs($admin)
        ->get(route('settings.webhooks.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Webhooks')
            ->where('availablePermissions', fn ($permissions): bool => collect($permissions)->contains(
                fn (array $permission): bool => $permission['key'] === PortalWebhook::PERMISSION_TSD_READ
            ) && collect($permissions)->contains(
                fn (array $permission): bool => $permission['key'] === PortalWebhook::PERMISSION_TSD_WRITE
            ))
        );
});

test('webhooks can list and create tsd scans', function () {
    $webhook = PortalWebhook::factory()->create([
        'permissions' => [
            PortalWebhook::PERMISSION_TSD_READ,
            PortalWebhook::PERMISSION_TSD_WRITE,
        ],
    ]);
    $webhook->issueToken('tsd-webhook-token');

    $existingScan = TsdQrScan::factory()->create([
        'source' => TsdQrScan::SOURCE_WEBHOOK,
        'portal_webhook_id' => $webhook->id,
        'scanned_by_user_id' => null,
    ]);

    $this->get(route('portal-webhooks.tsd.index', $webhook).'?token=tsd-webhook-token')
        ->assertSuccessful()
        ->assertJsonPath('webhook.id', $webhook->id)
        ->assertJsonPath('data.0.id', $existingScan->id)
        ->assertJsonPath('meta.total', 1);

    $this->postJson(route('portal-webhooks.tsd.store', $webhook).'?token=tsd-webhook-token', [
        'qr_code' => 'SHIP-QR-0091',
        'device_name' => 'TSD-Webhook',
        'location' => 'Dispatch area',
        'context' => 'shipping',
    ])->assertCreated()
        ->assertJsonPath('data.qr_code', 'SHIP-QR-0091')
        ->assertJsonPath('data.source', TsdQrScan::SOURCE_WEBHOOK);

    expect(TsdQrScan::query()->where('qr_code', 'SHIP-QR-0091')->where('portal_webhook_id', $webhook->id)->exists())->toBeTrue();
});

test('webhook tsd scans return resolved warehouse position when qr belongs to a warehouse item', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    app(WarehouseHierarchyManager::class)->create(
        warehouseHierarchyPayload('Склад webhook'),
        $user,
    );

    $place = WarehousePlace::query()->where('name', 'A-01-1-001')->firstOrFail();
    $warehouseItem = WarehouseItem::factory()->forPlace($place)->create([
        'name' => 'Маршрутизатор',
        'sku' => 'RTR-777',
        'qr_code' => 'WI-RTR-777',
        'quantity' => 3,
    ]);

    $webhook = PortalWebhook::factory()->create([
        'permissions' => [PortalWebhook::PERMISSION_TSD_WRITE],
    ]);
    $webhook->issueToken('tsd-resolve-token');

    $this->postJson(route('portal-webhooks.tsd.store', $webhook).'?token=tsd-resolve-token', [
        'qr_code' => $warehouseItem->qr_code,
    ])
        ->assertCreated()
        ->assertJsonPath('resolved.entity_type', 'item')
        ->assertJsonPath('resolved.title', 'Маршрутизатор')
        ->assertJsonPath('resolved.location.path', 'Склад webhook / Ряд A / Колонка 01 / Этаж 1 / A-01-1-001');
});
