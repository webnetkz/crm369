<?php

use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseItem;
use App\Models\WarehousePlace;
use App\Support\WarehouseHierarchyManager;
use Inertia\Testing\AssertableInertia as Assert;

test('verified users can open the warehouses module page and see hierarchy summary', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    app(WarehouseHierarchyManager::class)->create(
        warehouseHierarchyPayload(),
        $user,
    );

    $this->actingAs($user)
        ->get(route('warehouses.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('warehouses/Index')
            ->has('warehouses', 1)
            ->where('warehouses.0.name', 'Центральный склад')
            ->where('warehouses.0.area_sqm', 1250.5)
            ->where('warehouses.0.row_count', 2)
            ->where('warehouses.0.place_count', 3)
            ->where('summary.warehouse_count', 1)
            ->where('summary.row_count', 2)
            ->where('summary.column_count', 2)
            ->where('summary.floor_count', 2)
            ->where('summary.place_count', 3)
        );
});

test('verified users can open a concrete warehouse page and see detailed hierarchy', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $warehouse = app(WarehouseHierarchyManager::class)->create(
        warehouseHierarchyPayload(),
        $user,
    );

    $this->actingAs($user)
        ->get(route('warehouses.show', $warehouse))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('warehouses/Show')
            ->where('warehouse.name', 'Центральный склад')
            ->where('warehouse.row_count', 2)
            ->where('warehouse.item_count', 0)
            ->where('map.rows.0.name', 'Ряд A')
            ->where('map.rows.0.columns.0.name', 'Колонка 01')
            ->where('map.rows.0.columns.0.floors.0.places.0.name', 'A-01-1-001')
            ->where('inventoryQrCodes.data', [])
        );
});

test('verified users can scan a warehouse item qr from the warehouses tab and get exact location', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    app(WarehouseHierarchyManager::class)->create(
        warehouseHierarchyPayload(),
        $user,
    );

    $place = WarehousePlace::query()->where('name', 'A-01-1-001')->firstOrFail();
    $warehouseItem = WarehouseItem::factory()->forPlace($place)->create([
        'name' => 'Паллет с подшипниками',
        'sku' => 'PAL-001',
        'qr_code' => 'WI-PALLET-001',
        'quantity' => 4,
    ]);

    $this->actingAs($user)
        ->from(route('warehouses.index'))
        ->followingRedirects()
        ->post(route('warehouses.scan'), [
            'qr_code' => $warehouseItem->qr_code,
        ])
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('warehouses/Index')
            ->hasFlash('warehouseScanResult.entity_type', 'item')
            ->hasFlash('warehouseScanResult.title', 'Паллет с подшипниками')
            ->hasFlash('warehouseScanResult.location.path', 'Центральный склад / Ряд A / Колонка 01 / Этаж 1 / A-01-1-001')
        );
});

test('verified users can create a warehouse from the warehouses module', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->from(route('warehouses.index'))
        ->post(route('warehouses.store'), warehouseHierarchyPayload('Новый склад', 840.75))
        ->assertSessionHasNoErrors();

    $warehouse = Warehouse::query()->where('name', 'Новый склад')->first();

    expect($warehouse)->not->toBeNull();

    $this->assertNotNull($warehouse);

    $response->assertRedirect(route('warehouses.show', $warehouse));

    expect($warehouse)
        ->and($warehouse?->area_sqm)->toBe(840.75)
        ->and($warehouse?->created_by_user_id)->toBe($user->id)
        ->and($warehouse?->updated_by_user_id)->toBe($user->id)
        ->and($warehouse?->rows()->count())->toBe(2);
});

test('warehouses module is wired into the sidebar and built in menu items', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    Warehouse::factory()->create([
        'created_by_user_id' => $user->id,
    ]);

    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));
    $warehousePage = file_get_contents(resource_path('js/pages/warehouses/Index.vue'));
    $response = $this->actingAs($user)
        ->get(route('settings.menu.edit'))
        ->assertSuccessful();

    $builtInKeys = collect($response->inertiaProps('builtInItems'))->pluck('key');

    expect($sidebar)->toContain("isMenuItemVisible('warehouses')")
        ->and($sidebar)->toContain('title: t.value.warehouses.title')
        ->and($sidebar)->toContain('href: warehousesIndex()')
        ->and($sidebar)->toContain("key: 'warehouses'")
        ->and($warehousePage)->toContain('openCreateDialog')
        ->and($warehousePage)->toContain('storeWarehouse.url()')
        ->and($warehousePage)->toContain('showWarehouse.url(warehouse.id)')
        ->and($builtInKeys->all())->toContain('warehouses');
});
