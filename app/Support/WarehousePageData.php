<?php

namespace App\Support;

use App\Models\Warehouse;
use App\Models\WarehouseColumn;
use App\Models\WarehouseFloor;
use App\Models\WarehouseItem;
use App\Models\WarehousePlace;
use App\Models\WarehouseRow;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehousePageData
{
    /**
     * @return array{
     *     warehouses: array<int, array<string, int|float|string>>,
     *     summary: array<string, int|float>
     * }
     */
    public function index(): array
    {
        $warehouses = $this->warehousesWithHierarchyCounts();
        $warehouseData = $warehouses
            ->map(fn (Warehouse $warehouse): array => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'area_sqm' => $warehouse->area_sqm,
                'qr_code' => $warehouse->qr_code,
                'row_count' => $this->countAttribute($warehouse, 'row_count'),
                'column_count' => $this->countAttribute($warehouse, 'column_count'),
                'floor_count' => $this->countAttribute($warehouse, 'floor_count'),
                'place_count' => $this->countAttribute($warehouse, 'place_count'),
            ])
            ->values();

        return [
            'warehouses' => $warehouseData->all(),
            'summary' => [
                'warehouse_count' => $warehouseData->count(),
                'total_area_sqm' => round((float) $warehouseData->sum('area_sqm'), 2),
                'row_count' => (int) $warehouseData->sum('row_count'),
                'column_count' => (int) $warehouseData->sum('column_count'),
                'floor_count' => (int) $warehouseData->sum('floor_count'),
                'place_count' => (int) $warehouseData->sum('place_count'),
                'qr_code_count' => (int) $warehouseData->sum(
                    fn (array $warehouse): int => 1
                        + (int) $warehouse['row_count']
                        + (int) $warehouse['column_count']
                        + (int) $warehouse['floor_count']
                        + (int) $warehouse['place_count'],
                ),
            ],
        ];
    }

    /**
     * @return array{warehouse: array<string, mixed>, map: array{rows: array<int, array<string, mixed>>}}
     */
    public function show(Warehouse $warehouse): array
    {
        $warehouse->load([
            'creator:id,name,last_name',
            'updater:id,name,last_name',
            'rows.columns.floors' => fn (HasMany $query): HasMany => $query
                ->withCount(['places', 'items']),
        ]);

        $rows = $warehouse->rows
            ->map(fn (WarehouseRow $row): array => $this->serializeRow($row))
            ->values();

        return [
            'warehouse' => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'area_sqm' => $warehouse->area_sqm,
                'qr_code' => $warehouse->qr_code,
                'row_count' => $rows->count(),
                'column_count' => (int) $rows->sum('column_count'),
                'floor_count' => (int) $rows->sum('floor_count'),
                'place_count' => (int) $rows->sum('place_count'),
                'item_count' => (int) $rows->sum('item_count'),
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
                'rows' => $rows->all(),
            ],
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     places: array<int, array{
     *         id: int,
     *         name: string,
     *         item_count: int,
     *         items: array<int, array{id: int, name: string, sku: string|null, quantity: int}>
     *     }>
     * }
     */
    public function floor(Warehouse $warehouse, WarehouseFloor $floor): array
    {
        $belongsToWarehouse = $floor->column()
            ->whereIn(
                'warehouse_row_id',
                $warehouse->rows()->select('id'),
            )
            ->exists();

        abort_unless($belongsToWarehouse, 404);

        $floor->load([
            'places' => fn (HasMany $query): HasMany => $query
                ->select(['id', 'warehouse_floor_id', 'name', 'sort_order']),
            'places.items:id,warehouse_place_id,name,sku,quantity',
        ]);

        return [
            'id' => $floor->id,
            'places' => $floor->places
                ->map(fn (WarehousePlace $place): array => [
                    'id' => $place->id,
                    'name' => $place->name,
                    'item_count' => $place->items->count(),
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
        ];
    }

    /**
     * @return Collection<int, Warehouse>
     */
    private function warehousesWithHierarchyCounts(): Collection
    {
        $warehouseTable = (new Warehouse)->getTable();
        $rowTable = (new WarehouseRow)->getTable();
        $columnTable = (new WarehouseColumn)->getTable();
        $floorTable = (new WarehouseFloor)->getTable();
        $placeTable = (new WarehousePlace)->getTable();

        return Warehouse::query()
            ->select([
                "{$warehouseTable}.id",
                "{$warehouseTable}.name",
                "{$warehouseTable}.area_sqm",
                "{$warehouseTable}.qr_code",
            ])
            ->selectSub(
                WarehouseRow::query()
                    ->selectRaw('count(*)')
                    ->whereColumn("{$rowTable}.warehouse_id", "{$warehouseTable}.id"),
                'row_count',
            )
            ->selectSub(
                WarehouseColumn::query()
                    ->selectRaw('count(*)')
                    ->join($rowTable, "{$rowTable}.id", '=', "{$columnTable}.warehouse_row_id")
                    ->whereColumn("{$rowTable}.warehouse_id", "{$warehouseTable}.id"),
                'column_count',
            )
            ->selectSub(
                WarehouseFloor::query()
                    ->selectRaw('count(*)')
                    ->join($columnTable, "{$columnTable}.id", '=', "{$floorTable}.warehouse_column_id")
                    ->join($rowTable, "{$rowTable}.id", '=', "{$columnTable}.warehouse_row_id")
                    ->whereColumn("{$rowTable}.warehouse_id", "{$warehouseTable}.id"),
                'floor_count',
            )
            ->selectSub(
                WarehousePlace::query()
                    ->selectRaw('count(*)')
                    ->join($floorTable, "{$floorTable}.id", '=', "{$placeTable}.warehouse_floor_id")
                    ->join($columnTable, "{$columnTable}.id", '=', "{$floorTable}.warehouse_column_id")
                    ->join($rowTable, "{$rowTable}.id", '=', "{$columnTable}.warehouse_row_id")
                    ->whereColumn("{$rowTable}.warehouse_id", "{$warehouseTable}.id"),
                'place_count',
            )
            ->orderBy("{$warehouseTable}.name")
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRow(WarehouseRow $row): array
    {
        $columns = $row->columns
            ->map(fn (WarehouseColumn $column): array => $this->serializeColumn($column))
            ->values();

        return [
            'id' => $row->id,
            'name' => $row->name,
            'column_count' => $columns->count(),
            'floor_count' => (int) $columns->sum('floor_count'),
            'place_count' => (int) $columns->sum('place_count'),
            'item_count' => (int) $columns->sum('item_count'),
            'columns' => $columns->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeColumn(WarehouseColumn $column): array
    {
        $floors = $column->floors
            ->map(fn (WarehouseFloor $floor): array => [
                'id' => $floor->id,
                'name' => $floor->name,
                'place_count' => $this->countAttribute($floor, 'places_count'),
                'item_count' => $this->countAttribute($floor, 'items_count'),
                'places' => [],
            ])
            ->values();

        return [
            'id' => $column->id,
            'name' => $column->name,
            'floor_count' => $floors->count(),
            'place_count' => (int) $floors->sum('place_count'),
            'item_count' => (int) $floors->sum('item_count'),
            'floors' => $floors->all(),
        ];
    }

    private function countAttribute(Warehouse|WarehouseFloor $model, string $attribute): int
    {
        return (int) $model->getAttribute($attribute);
    }
}
