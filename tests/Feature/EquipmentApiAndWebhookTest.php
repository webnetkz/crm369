<?php

use App\Models\ApiAccessToken;
use App\Models\EquipmentItem;
use App\Models\PortalSetting;
use App\Models\PortalWebhook;
use App\Models\User;
use App\Models\UserGroup;
use App\Support\ApiCatalog;

function equipmentApiAdministratorsGroup(): UserGroup
{
    return UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);
}

function issueEquipmentApiTokenFor(User $user, array $permissions): string
{
    $plainTextToken = ApiAccessToken::generatePlainTextToken();

    ApiAccessToken::query()->create([
        'user_id' => $user->id,
        'name' => 'Equipment API token',
        ...ApiAccessToken::tokenAttributes($plainTextToken),
        'permissions' => $permissions,
    ]);

    return $plainTextToken;
}

function equipmentApiHeadersFor(string $token): array
{
    return [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ];
}

test('equipment api endpoints list show create and update items', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => equipmentApiAdministratorsGroup()->id,
    ]);
    $responsibleUser = User::factory()->create();
    $issuedUser = User::factory()->create();

    $existingItem = EquipmentItem::factory()->create([
        'name' => 'API Existing Scanner',
        'qr_code' => 'EQ-API-EXISTING-01',
        'responsible_user_id' => $responsibleUser->id,
        'created_by_user_id' => $admin->id,
        'updated_by_user_id' => $admin->id,
    ]);

    $token = issueEquipmentApiTokenFor($admin, [
        ApiAccessToken::PERMISSION_EQUIPMENT_READ,
        ApiAccessToken::PERMISSION_EQUIPMENT_WRITE,
    ]);

    $this->withHeaders(equipmentApiHeadersFor($token))
        ->getJson(route('api.v1.equipment.index'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $existingItem->id)
        ->assertJsonPath('data.0.name', 'API Existing Scanner')
        ->assertJsonPath('data.0.responsible_user.id', $responsibleUser->id)
        ->assertJsonPath('status_options.0.value', EquipmentItem::STATUS_ON_BALANCE);

    $this->withHeaders(equipmentApiHeadersFor($token))
        ->getJson(route('api.v1.equipment.show', $existingItem))
        ->assertOk()
        ->assertJsonPath('data.id', $existingItem->id)
        ->assertJsonPath('data.qr_code', 'EQ-API-EXISTING-01');

    $createResponse = $this->withHeaders(equipmentApiHeadersFor($token))
        ->postJson(route('api.v1.equipment.store'), [
            'name' => 'API Created Laptop',
            'qr_code' => 'EQ-API-CREATED-01',
            'status' => EquipmentItem::STATUS_ON_BALANCE,
            'responsible_user_id' => $responsibleUser->id,
        ])
        ->assertCreated()
        ->assertJsonPath('message', __('ui.equipment.created_success'))
        ->assertJsonPath('data.name', 'API Created Laptop')
        ->assertJsonPath('data.created_by.id', $admin->id)
        ->assertJsonPath('data.updated_by.id', $admin->id);

    $createdItemId = $createResponse->json('data.id');

    $this->withHeaders(equipmentApiHeadersFor($token))
        ->patchJson(route('api.v1.equipment.update', $createdItemId), [
            'name' => 'API Created Laptop Updated',
            'qr_code' => 'EQ-API-CREATED-01',
            'status' => EquipmentItem::STATUS_ISSUED,
            'responsible_user_id' => $responsibleUser->id,
            'issued_to_user_id' => $issuedUser->id,
        ])
        ->assertOk()
        ->assertJsonPath('message', __('ui.equipment.updated_success'))
        ->assertJsonPath('data.name', 'API Created Laptop Updated')
        ->assertJsonPath('data.status', EquipmentItem::STATUS_ISSUED)
        ->assertJsonPath('data.issued_to_user.id', $issuedUser->id);

    expect(EquipmentItem::query()->findOrFail($createdItemId)->status)->toBe(EquipmentItem::STATUS_ISSUED)
        ->and(EquipmentItem::query()->findOrFail($createdItemId)->issued_to_user_id)->toBe($issuedUser->id);
});

test('equipment api endpoints enforce permissions and disabled module state', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => equipmentApiAdministratorsGroup()->id,
    ]);

    $readOnlyToken = issueEquipmentApiTokenFor($admin, [
        ApiAccessToken::PERMISSION_EQUIPMENT_READ,
    ]);

    $this->withHeaders(equipmentApiHeadersFor($readOnlyToken))
        ->postJson(route('api.v1.equipment.store'), [
            'name' => 'Forbidden Equipment',
        ])
        ->assertForbidden();

    $writeOnlyToken = issueEquipmentApiTokenFor($admin, [
        ApiAccessToken::PERMISSION_EQUIPMENT_WRITE,
    ]);

    $this->withHeaders(equipmentApiHeadersFor($writeOnlyToken))
        ->getJson(route('api.v1.equipment.index'))
        ->assertForbidden();

    PortalSetting::current()->update([
        'disabled_modules' => ['equipment'],
    ]);

    $fullToken = issueEquipmentApiTokenFor($admin, [
        ApiAccessToken::PERMISSION_EQUIPMENT_READ,
        ApiAccessToken::PERMISSION_EQUIPMENT_WRITE,
    ]);

    $this->withHeaders(equipmentApiHeadersFor($fullToken))
        ->getJson(route('api.v1.equipment.index'))
        ->assertNotFound();
});

test('equipment webhook read endpoints expose payload and endpoint templates', function () {
    $creator = User::factory()->create();
    $responsibleUser = User::factory()->create();

    $webhook = PortalWebhook::factory()->create([
        'created_by_user_id' => $creator->id,
        'permissions' => [PortalWebhook::PERMISSION_EQUIPMENT_READ],
    ]);
    $webhook->issueToken('equipment-read-token');

    $equipmentItem = EquipmentItem::factory()->create([
        'name' => 'Webhook Scanner',
        'qr_code' => 'EQ-WEBHOOK-READ-01',
        'responsible_user_id' => $responsibleUser->id,
        'created_by_user_id' => $creator->id,
        'updated_by_user_id' => $creator->id,
    ]);

    $this->get(route('portal-webhooks.invoke', $webhook).'?token=equipment-read-token')
        ->assertOk()
        ->assertJsonPath('equipment_items.0.id', $equipmentItem->id)
        ->assertJsonPath('equipment_items.0.name', 'Webhook Scanner')
        ->assertJsonPath(
            'endpoints.equipment.index',
            route('portal-webhooks.equipment.index', $webhook).'?token=equipment-read-token',
        )
        ->assertJsonPath(
            'endpoints.equipment.show_template',
            route('portal-webhooks.equipment.show', [
                'portalWebhook' => $webhook,
                'equipmentItem' => '__EQUIPMENT_ID__',
            ]).'?token=equipment-read-token',
        );

    $this->get(route('portal-webhooks.equipment.index', $webhook).'?token=equipment-read-token')
        ->assertOk()
        ->assertJsonPath('webhook.id', $webhook->id)
        ->assertJsonPath('data.0.id', $equipmentItem->id)
        ->assertJsonCount(5, 'status_options');

    $this->get(route('portal-webhooks.equipment.show', [$webhook, $equipmentItem]).'?token=equipment-read-token')
        ->assertOk()
        ->assertJsonPath('data.id', $equipmentItem->id)
        ->assertJsonPath('data.responsible_user.id', $responsibleUser->id);
});

test('equipment webhook write endpoints can create update and enforce permissions', function () {
    $creator = User::factory()->create();
    $responsibleUser = User::factory()->create();
    $issuedUser = User::factory()->create();

    $webhook = PortalWebhook::factory()->create([
        'created_by_user_id' => $creator->id,
        'permissions' => [PortalWebhook::PERMISSION_EQUIPMENT_WRITE],
    ]);
    $webhook->issueToken('equipment-write-token');

    $createResponse = $this->postJson(route('portal-webhooks.equipment.store', $webhook).'?token=equipment-write-token', [
        'name' => 'Webhook Created Printer',
        'qr_code' => 'EQ-WEBHOOK-WRITE-01',
        'status' => EquipmentItem::STATUS_ON_BALANCE,
        'responsible_user_id' => $responsibleUser->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Webhook Created Printer')
        ->assertJsonPath('data.created_by.id', $creator->id)
        ->assertJsonPath('data.updated_by.id', $creator->id);

    $createdItemId = $createResponse->json('data.id');

    $this->patchJson(route('portal-webhooks.equipment.update', [$webhook, $createdItemId]).'?token=equipment-write-token', [
        'name' => 'Webhook Created Printer Updated',
        'qr_code' => 'EQ-WEBHOOK-WRITE-01',
        'status' => EquipmentItem::STATUS_ISSUED,
        'responsible_user_id' => $responsibleUser->id,
        'issued_to_user_id' => $issuedUser->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Webhook Created Printer Updated')
        ->assertJsonPath('data.status', EquipmentItem::STATUS_ISSUED)
        ->assertJsonPath('data.issued_to_user.id', $issuedUser->id);

    $forbiddenWebhook = PortalWebhook::factory()->create([
        'permissions' => [PortalWebhook::PERMISSION_CONTACTS_READ],
    ]);
    $forbiddenWebhook->issueToken('equipment-forbidden-token');

    $this->postJson(route('portal-webhooks.equipment.store', $forbiddenWebhook).'?token=equipment-forbidden-token', [
        'name' => 'Blocked Equipment',
    ])
        ->assertForbidden()
        ->assertJsonPath('message', __('ui.webhooks.error_missing_permission'));
});

test('equipment webhook endpoints respect disabled module state', function () {
    PortalSetting::current()->update([
        'disabled_modules' => ['equipment'],
    ]);

    $webhook = PortalWebhook::factory()->create([
        'permissions' => [PortalWebhook::PERMISSION_EQUIPMENT_READ],
    ]);
    $webhook->issueToken('equipment-disabled-token');

    $this->get(route('portal-webhooks.equipment.index', $webhook).'?token=equipment-disabled-token')
        ->assertNotFound();
});

test('equipment api catalog and webhook settings documentation include equipment endpoints', function () {
    $admin = User::factory()->create([
        'user_group_id' => equipmentApiAdministratorsGroup()->id,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('settings.webhooks.edit'))
        ->assertSuccessful();

    expect($response->inertiaProps('documentation.equipment_index_url'))->toBe(url('/portal-webhooks').'/{webhook_id}/equipment')
        ->and($response->inertiaProps('documentation.equipment_show_url'))->toBe(url('/portal-webhooks').'/{webhook_id}/equipment/{equipment_id}')
        ->and(collect($response->inertiaProps('availablePermissions'))->pluck('key')->all())
        ->toContain(PortalWebhook::PERMISSION_EQUIPMENT_READ, PortalWebhook::PERMISSION_EQUIPMENT_WRITE);

    $equipmentSection = collect(app(ApiCatalog::class)->sections())
        ->firstWhere('title', __('ui.api.section_equipment'));

    expect($equipmentSection)->not->toBeNull()
        ->and(collect($equipmentSection['endpoints'])->pluck('path')->all())
        ->toBe([
            '/api/v1/equipment',
            '/api/v1/equipment/{equipmentItem}',
            '/api/v1/equipment',
            '/api/v1/equipment/{equipmentItem}',
        ])
        ->and(collect($equipmentSection['endpoints'])->pluck('permission')->all())
        ->toBe([
            ApiAccessToken::PERMISSION_EQUIPMENT_READ,
            ApiAccessToken::PERMISSION_EQUIPMENT_READ,
            ApiAccessToken::PERMISSION_EQUIPMENT_WRITE,
            ApiAccessToken::PERMISSION_EQUIPMENT_WRITE,
        ]);
});
