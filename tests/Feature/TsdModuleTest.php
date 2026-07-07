<?php

use App\Models\ApiAccessToken;
use App\Models\PortalWebhook;
use App\Models\TsdQrScan;
use App\Models\User;
use App\Models\UserGroup;
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

test('tsd is wired into the sidebar and built in menu items', function () {
    $user = User::factory()->create();

    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));
    $response = $this->actingAs($user)
        ->get(route('settings.menu.edit'))
        ->assertSuccessful();

    $builtInKeys = collect($response->inertiaProps('builtInItems'))->pluck('key');

    expect($sidebar)->toContain("isMenuItemVisible('tsd')")
        ->and($sidebar)->toContain('title: t.value.tsd.title')
        ->and($sidebar)->toContain('href: tsdIndex()')
        ->and($builtInKeys->all())->toContain('tsd');
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

test('webhook settings include tsd documentation and permissions', function () {
    $admin = User::factory()->create([
        'user_group_id' => tsdApiAdministratorsGroup()->id,
    ]);

    $this->actingAs($admin)
        ->get(route('settings.webhooks.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Webhooks')
            ->where('documentation.tsd_index_url', url('/portal-webhooks').'/{webhook_id}/tsd/scans')
            ->where('documentation.tsd_store_url', url('/portal-webhooks').'/{webhook_id}/tsd/scans')
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
