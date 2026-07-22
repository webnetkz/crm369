<?php

use App\Models\User;
use App\Models\WarehouseColumn;
use App\Models\WarehouseFloor;
use App\Models\WarehouseItem;
use App\Models\WarehousePlace;
use App\Models\WarehouseRow;
use App\Support\WarehouseHierarchyManager;
use Inertia\Testing\AssertableInertia as Assert;

test('warehouse hierarchy qr codes can be viewed with their contents', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $warehouse = app(WarehouseHierarchyManager::class)->create(
        warehouseHierarchyPayload(),
        $user,
    );
    $row = WarehouseRow::query()->where('warehouse_id', $warehouse->id)->firstOrFail();
    $column = WarehouseColumn::query()->where('warehouse_row_id', $row->id)->firstOrFail();
    $floor = WarehouseFloor::query()->where('warehouse_column_id', $column->id)->firstOrFail();
    $place = WarehousePlace::query()->where('warehouse_floor_id', $floor->id)->firstOrFail();
    $warehouseItem = WarehouseItem::factory()->forPlace($place)->create([
        'name' => 'Подшипник SKF 6204',
        'sku' => 'SKF-6204',
        'quantity' => 8,
    ]);

    $entities = [
        'warehouse' => $warehouse,
        'row' => $row,
        'column' => $column,
        'floor' => $floor,
        'place' => $place,
    ];

    foreach ($entities as $entityType => $entity) {
        $this->actingAs($user)
            ->getJson(route('warehouses.qr.show', $entity->qr_code))
            ->assertSuccessful()
            ->assertJsonPath('data.entity_type', $entityType)
            ->assertJsonPath('data.qr_code', $entity->qr_code)
            ->assertJsonPath('data.warehouse.id', $warehouse->id)
            ->assertJsonPath('data.contents.0.id', $warehouseItem->id)
            ->assertJsonPath('data.contents.0.location.path', 'Центральный склад / Ряд A / Колонка 01 / Этаж 1 / A-01-1-001')
            ->assertJson(fn ($json) => $json
                ->whereType('data.qr_code_svg_data_uri', 'string')
                ->where('data.contents_truncated', false)
                ->etc()
            );
    }
});

test('warehouse item qr code shows its exact storage location', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $warehouse = app(WarehouseHierarchyManager::class)->create(
        warehouseHierarchyPayload(),
        $user,
    );
    $place = WarehousePlace::query()->where('name', 'A-01-1-001')->firstOrFail();
    $warehouseItem = WarehouseItem::factory()->forPlace($place)->create([
        'name' => 'Серверный блок',
        'sku' => 'SRV-001',
        'quantity' => 1,
    ]);

    $this->actingAs($user)
        ->getJson(route('warehouses.qr.show', $warehouseItem->qr_code))
        ->assertSuccessful()
        ->assertJsonPath('data.entity_type', 'item')
        ->assertJsonPath('data.title', 'Серверный блок')
        ->assertJsonPath('data.details.sku', 'SRV-001')
        ->assertJsonPath('data.location.path', 'Центральный склад / Ряд A / Колонка 01 / Этаж 1 / A-01-1-001')
        ->assertJsonPath('data.warehouse.id', $warehouse->id)
        ->assertJsonCount(0, 'data.contents');
});

test('warehouse page exposes every hierarchy qr code and opens a requested qr', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $warehouse = app(WarehouseHierarchyManager::class)->create(
        warehouseHierarchyPayload(),
        $user,
    );
    $row = WarehouseRow::query()->where('warehouse_id', $warehouse->id)->firstOrFail();
    $column = WarehouseColumn::query()->where('warehouse_row_id', $row->id)->firstOrFail();
    $floor = WarehouseFloor::query()->where('warehouse_column_id', $column->id)->firstOrFail();
    $place = WarehousePlace::query()->where('warehouse_floor_id', $floor->id)->firstOrFail();
    $warehouseItem = WarehouseItem::factory()->forPlace($place)->create();

    $this->actingAs($user)
        ->get(route('warehouses.show', [
            'warehouse' => $warehouse,
            'qr_code' => $warehouse->qr_code,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('warehouses/Show')
            ->where('activeQrCode', $warehouse->qr_code)
            ->where('map.rows.0.qr_code', $row->qr_code)
            ->where('map.rows.0.columns.0.qr_code', $column->qr_code)
            ->where('map.rows.0.columns.0.floors.0.qr_code', $floor->qr_code)
            ->where('inventoryQrCodes.data.0.qr_code', $warehouseItem->qr_code)
        );

    $this->actingAs($user)
        ->getJson(route('warehouses.floors.show', [$warehouse, $floor]))
        ->assertSuccessful()
        ->assertJsonPath('data.places.0.qr_code', $place->qr_code)
        ->assertJsonPath('data.places.0.items.0.qr_code', $warehouseItem->qr_code);
});

test('tsd scan redirects a warehouse item to its qr details', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $warehouse = app(WarehouseHierarchyManager::class)->create(
        warehouseHierarchyPayload(),
        $user,
    );
    $place = WarehousePlace::query()->where('name', 'A-01-1-001')->firstOrFail();
    $warehouseItem = WarehouseItem::factory()->forPlace($place)->create();

    $this->actingAs($user)
        ->post(route('tsd.store'), [
            'qr_code' => $warehouseItem->qr_code,
        ])
        ->assertRedirect(route('warehouses.show', [
            'warehouse' => $warehouse,
            'qr_code' => $warehouseItem->qr_code,
        ]));
});

test('warehouse qr details require warehouse access and reject unknown codes', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this->getJson(route('warehouses.qr.show', 'WH-UNKNOWN'))
        ->assertRedirect(route('login'));

    $this->actingAs($user)
        ->getJson(route('warehouses.qr.show', 'WH-UNKNOWN'))
        ->assertNotFound();
});
