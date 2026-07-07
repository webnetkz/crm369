<?php

use App\Models\PortalWebhook;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\WarehouseItem;
use App\Models\WarehousePlace;
use Illuminate\Testing\Fluent\AssertableJson;
use Inertia\Testing\AssertableInertia as Assert;

function warehouseWebhookAdministratorsGroup(): UserGroup
{
    return UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);
}

test('warehouse webhook endpoints expose hierarchy, qr codes, and write operations', function () {
    $webhook = PortalWebhook::factory()->create([
        'permissions' => [
            PortalWebhook::PERMISSION_WAREHOUSES_READ,
            PortalWebhook::PERMISSION_WAREHOUSES_WRITE,
        ],
    ]);
    $webhook->issueToken('warehouses-webhook-token');

    $createResponse = $this->postJson(
        route('portal-webhooks.warehouses.store', $webhook).'?token=warehouses-webhook-token',
        warehouseHierarchyPayload('Webhook склад', 870.4),
    )
        ->assertCreated()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('message', __('ui.warehouses.created_success'))
            ->where('data.name', 'Webhook склад')
            ->where('data.row_count', 2)
            ->whereType('data.qr_code', 'string')
            ->whereType('data.rows.0.columns.0.floors.0.places.0.qr_code', 'string')
            ->etc()
        );

    $warehouseId = $createResponse->json('data.id');

    $this->get(route('portal-webhooks.invoke', $webhook).'?token=warehouses-webhook-token')
        ->assertOk()
        ->assertJsonPath(
            'endpoints.warehouses.index',
            route('portal-webhooks.warehouses.index', $webhook).'?token=warehouses-webhook-token',
        )
        ->assertJsonPath(
            'endpoints.warehouses.items_template',
            route('portal-webhooks.warehouses.items', [
                'portalWebhook' => $webhook,
                'warehouse' => '__WAREHOUSE_ID__',
            ]).'?token=warehouses-webhook-token',
        )
        ->assertJsonPath('warehouses.0.id', $warehouseId);

    $this->get(route('portal-webhooks.warehouses.index', $webhook).'?token=warehouses-webhook-token')
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('meta.total', 1)
            ->where('data.0.id', $warehouseId)
            ->etc()
        );

    $this->get(route('portal-webhooks.warehouses.show', [$webhook, $warehouseId]).'?token=warehouses-webhook-token')
        ->assertOk()
        ->assertJsonPath('data.rows.0.columns.0.floors.0.places.1.name', 'A-01-1-002');

    $place = WarehousePlace::query()->where('name', 'A-01-1-001')->firstOrFail();

    WarehouseItem::factory()->forPlace($place)->create([
        'name' => 'Контроллер линии',
        'sku' => 'PLC-900',
        'qr_code' => 'WI-PLC-900',
        'quantity' => 1,
    ]);

    $this->get(route('portal-webhooks.warehouses.items', [$webhook, $warehouseId]).'?token=warehouses-webhook-token')
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('meta.total', 1)
            ->where('data.0.name', 'Контроллер линии')
            ->where('data.0.location.path', 'Webhook склад / Ряд A / Колонка 01 / Этаж 1 / A-01-1-001')
            ->etc()
        );

    $this->patchJson(
        route('portal-webhooks.warehouses.update', [$webhook, $warehouseId]).'?token=warehouses-webhook-token',
        [
            'name' => 'Webhook склад 2.0',
            'rows' => [
                [
                    'name' => 'Ряд Z',
                    'columns' => [
                        [
                            'name' => 'Колонка 09',
                            'floors' => [
                                [
                                    'name' => 'Этаж 3',
                                    'places' => [
                                        ['name' => 'Z-09-3-001'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    )
        ->assertOk()
        ->assertJsonPath('data.name', 'Webhook склад 2.0')
        ->assertJsonPath('data.row_count', 1)
        ->assertJsonPath('data.rows.0.name', 'Ряд Z');

    $this->deleteJson(
        route('portal-webhooks.warehouses.destroy', [$webhook, $warehouseId]).'?token=warehouses-webhook-token',
    )
        ->assertOk()
        ->assertJsonPath('data.id', $warehouseId);
});

test('warehouse webhook write endpoints require write permission', function () {
    $webhook = PortalWebhook::factory()->create([
        'permissions' => [PortalWebhook::PERMISSION_WAREHOUSES_READ],
    ]);
    $webhook->issueToken('warehouses-read-only-token');

    $this->postJson(
        route('portal-webhooks.warehouses.store', $webhook).'?token=warehouses-read-only-token',
        warehouseHierarchyPayload('Недоступный склад'),
    )->assertForbidden();
});

test('webhook settings include warehouse item qr endpoint in documentation', function () {
    $admin = User::factory()->create([
        'user_group_id' => warehouseWebhookAdministratorsGroup()->id,
    ]);

    $this->actingAs($admin)
        ->get(route('settings.webhooks.documentation.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/WebhookDocumentation')
            ->where('documentation.warehouses_items_url', url('/portal-webhooks').'/{webhook_id}/warehouses/{warehouse_id}/items')
        );
});
