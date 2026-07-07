<?php

use App\Models\PortalWebhook;
use Illuminate\Testing\Fluent\AssertableJson;

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
