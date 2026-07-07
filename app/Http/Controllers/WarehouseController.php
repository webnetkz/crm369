<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTsdQrScanRequest;
use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Resources\ApiResolvedQrCodeResource;
use App\Http\Resources\ApiWarehouseItemResource;
use App\Models\TsdQrScan;
use App\Models\Warehouse;
use App\Models\WarehouseColumn;
use App\Models\WarehouseFloor;
use App\Models\WarehouseItem;
use App\Models\WarehousePlace;
use App\Models\WarehouseRow;
use App\Support\PaginationData;
use App\Support\QrCodeResolver;
use App\Support\TsdQrScanManager;
use App\Support\WarehouseHierarchyManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    /**
     * @var array<int, int>
     */
    private const array INVENTORY_PER_PAGE_OPTIONS = [12, 24, 48];

    private const int DEFAULT_INVENTORY_PER_PAGE = 12;

    public function index(): Response
    {
        $warehouses = Warehouse::query()
            ->with([
                'rows.columns.floors.places',
            ])
            ->orderBy('name')
            ->get();

        $warehouseData = $warehouses
            ->map(fn (Warehouse $warehouse): array => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'area_sqm' => $warehouse->area_sqm,
                'qr_code' => $warehouse->qr_code,
                'row_count' => $warehouse->rowCount(),
                'column_count' => $warehouse->columnCount(),
                'floor_count' => $warehouse->floorCount(),
                'place_count' => $warehouse->placeCount(),
            ])
            ->values()
            ->all();

        return Inertia::render('warehouses/Index', [
            'warehouses' => $warehouseData,
            'summary' => [
                'warehouse_count' => $warehouses->count(),
                'total_area_sqm' => round($warehouses->sum('area_sqm'), 2),
                'row_count' => $warehouses->sum(fn (Warehouse $warehouse): int => $warehouse->rowCount()),
                'column_count' => $warehouses->sum(fn (Warehouse $warehouse): int => $warehouse->columnCount()),
                'floor_count' => $warehouses->sum(fn (Warehouse $warehouse): int => $warehouse->floorCount()),
                'place_count' => $warehouses->sum(fn (Warehouse $warehouse): int => $warehouse->placeCount()),
                'qr_code_count' => $warehouses->sum(fn (Warehouse $warehouse): int => 1 + $warehouse->rowCount() + $warehouse->columnCount() + $warehouse->floorCount() + $warehouse->placeCount()),
            ],
        ]);
    }

    public function show(Warehouse $warehouse): Response
    {
        $warehouse->load([
            'creator:id,name,last_name',
            'updater:id,name,last_name',
            'rows.columns.floors.places.items',
        ]);

        $inventoryPerPage = $this->resolveInventoryPerPage(request());
        $inventoryQrCodes = $this->warehouseInventoryQuery($warehouse)
            ->paginate($inventoryPerPage, ['*'], 'items_page')
            ->withQueryString()
            ->through(fn (WarehouseItem $warehouseItem): array => (new ApiWarehouseItemResource($warehouseItem))->resolve());

        return Inertia::render('warehouses/Show', [
            'warehouse' => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'area_sqm' => $warehouse->area_sqm,
                'qr_code' => $warehouse->qr_code,
                'row_count' => $warehouse->rowCount(),
                'column_count' => $warehouse->columnCount(),
                'floor_count' => $warehouse->floorCount(),
                'place_count' => $warehouse->placeCount(),
                'item_count' => $warehouse->itemCount(),
                'created_at' => $warehouse->created_at?->toISOString(),
                'updated_at' => $warehouse->updated_at?->toISOString(),
                'created_by' => $warehouse->creator
                    ? [
                        'id' => $warehouse->creator->id,
                        'name' => $warehouse->creator->name,
                        'last_name' => $warehouse->creator->last_name,
                    ]
                    : null,
                'updated_by' => $warehouse->updater
                    ? [
                        'id' => $warehouse->updater->id,
                        'name' => $warehouse->updater->name,
                        'last_name' => $warehouse->updater->last_name,
                    ]
                    : null,
            ],
            'map' => [
                'rows' => $warehouse->rows
                    ->map(fn (WarehouseRow $row): array => [
                        'id' => $row->id,
                        'name' => $row->name,
                        'column_count' => $row->columnCount(),
                        'floor_count' => $row->floorCount(),
                        'place_count' => $row->placeCount(),
                        'item_count' => $row->itemCount(),
                        'columns' => $row->columns
                            ->map(fn (WarehouseColumn $column): array => [
                                'id' => $column->id,
                                'name' => $column->name,
                                'floor_count' => $column->floorCount(),
                                'place_count' => $column->placeCount(),
                                'item_count' => $column->itemCount(),
                                'floors' => $column->floors
                                    ->map(fn (WarehouseFloor $floor): array => [
                                        'id' => $floor->id,
                                        'name' => $floor->name,
                                        'place_count' => $floor->placeCount(),
                                        'item_count' => $floor->itemCount(),
                                        'places' => $floor->places
                                            ->map(fn (WarehousePlace $place): array => [
                                                'id' => $place->id,
                                                'name' => $place->name,
                                                'item_count' => $place->itemCount(),
                                                'items' => $place->items
                                                    ->map(fn (WarehouseItem $warehouseItem): array => [
                                                        'id' => $warehouseItem->id,
                                                        'name' => $warehouseItem->name,
                                                        'sku' => $warehouseItem->sku,
                                                        'quantity' => $warehouseItem->quantity,
                                                    ])
                                                    ->values()
                                                    ->all(),
                                            ])
                                            ->values()
                                            ->all(),
                                    ])
                                    ->values()
                                    ->all(),
                            ])
                            ->values()
                            ->all(),
                    ])
                    ->values()
                    ->all(),
            ],
            'inventoryQrCodes' => PaginationData::from($inventoryQrCodes),
            'inventoryPerPageOptions' => self::INVENTORY_PER_PAGE_OPTIONS,
            'filters' => [
                'items_per_page' => $inventoryPerPage,
            ],
        ]);
    }

    public function store(
        StoreWarehouseRequest $request,
        WarehouseHierarchyManager $warehouseHierarchyManager,
    ): RedirectResponse {
        $warehouse = $warehouseHierarchyManager->create(
            $request->payload(),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.warehouses.created_success')]);

        return to_route('warehouses.show', $warehouse);
    }

    public function scan(
        StoreTsdQrScanRequest $request,
        TsdQrScanManager $tsdQrScanManager,
        QrCodeResolver $qrCodeResolver,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $tsdQrScanManager->create(
            payload: [
                ...$request->scanPayload(),
                'context' => $request->validated('context') ?? 'warehouse_lookup',
            ],
            source: TsdQrScan::SOURCE_WEB,
            user: $user,
        );

        $resolvedQrCode = $qrCodeResolver->resolve($request->validated('qr_code'));

        if ($resolvedQrCode !== null) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('ui.warehouses.scan_found_success'),
            ]);
            Inertia::flash('warehouseScanResult', (new ApiResolvedQrCodeResource($resolvedQrCode))->resolve());
        } else {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('ui.warehouses.scan_not_found'),
            ]);
            Inertia::flash('warehouseScanResult', null);
        }

        return back();
    }

    private function resolveInventoryPerPage(Request $request): int
    {
        $itemsPerPage = (int) $request->integer('items_per_page', self::DEFAULT_INVENTORY_PER_PAGE);

        return in_array($itemsPerPage, self::INVENTORY_PER_PAGE_OPTIONS, true)
            ? $itemsPerPage
            : self::DEFAULT_INVENTORY_PER_PAGE;
    }

    /**
     * @return Builder<WarehouseItem>
     */
    private function warehouseInventoryQuery(Warehouse $warehouse): Builder
    {
        return WarehouseItem::query()
            ->with('place.floor.column.row.warehouse')
            ->whereHas('place.floor.column.row', fn ($query) => $query->where('warehouse_id', $warehouse->id))
            ->orderBy('name')
            ->orderBy('id');
    }
}
