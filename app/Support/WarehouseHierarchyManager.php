<?php

namespace App\Support;

use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseColumn;
use App\Models\WarehouseFloor;
use App\Models\WarehouseRow;
use Illuminate\Support\Facades\DB;

class WarehouseHierarchyManager
{
    /**
     * @param  array{
     *     name: string,
     *     area_sqm: float|int|string,
     *     rows: array<int, array{name: string, columns: array<int, array{name: string, floors: array<int, array{name: string, places: array<int, array{name: string}>}>}>}>
     * }  $payload
     */
    public function create(array $payload, ?User $user = null): Warehouse
    {
        /** @var Warehouse $warehouse */
        $warehouse = DB::transaction(function () use ($payload, $user): Warehouse {
            $warehouse = Warehouse::query()->create([
                'name' => $payload['name'],
                'area_sqm' => (float) $payload['area_sqm'],
                'created_by_user_id' => $user?->id,
                'updated_by_user_id' => $user?->id,
            ]);

            $this->syncRows($warehouse, $payload['rows']);

            return $warehouse;
        });

        return $this->freshWarehouse($warehouse);
    }

    /**
     * @param  array{
     *     name?: string,
     *     area_sqm?: float|int|string,
     *     rows?: array<int, array{name: string, columns: array<int, array{name: string, floors: array<int, array{name: string, places: array<int, array{name: string}>}>}>}>
     * }  $payload
     */
    public function update(Warehouse $warehouse, array $payload, ?User $user = null): Warehouse
    {
        DB::transaction(function () use ($warehouse, $payload, $user): void {
            $warehouseAttributes = [];

            if (array_key_exists('name', $payload)) {
                $warehouseAttributes['name'] = $payload['name'];
            }

            if (array_key_exists('area_sqm', $payload)) {
                $warehouseAttributes['area_sqm'] = (float) $payload['area_sqm'];
            }

            if ($user !== null) {
                $warehouseAttributes['updated_by_user_id'] = $user->id;
            }

            if ($warehouseAttributes !== []) {
                $warehouse->update($warehouseAttributes);
            }

            if (array_key_exists('rows', $payload)) {
                $this->syncRows($warehouse, $payload['rows']);
            }
        });

        return $this->freshWarehouse($warehouse);
    }

    /**
     * @param  array<int, array{name: string, columns: array<int, array{name: string, floors: array<int, array{name: string, places: array<int, array{name: string}>}>}>}>  $rows
     */
    private function syncRows(Warehouse $warehouse, array $rows): void
    {
        $warehouse->rows()->delete();

        foreach ($rows as $rowIndex => $rowPayload) {
            $row = $warehouse->rows()->create([
                'name' => $rowPayload['name'],
                'sort_order' => $rowIndex + 1,
            ]);

            $this->syncColumns($row, $rowPayload['columns']);
        }
    }

    /**
     * @param  array<int, array{name: string, floors: array<int, array{name: string, places: array<int, array{name: string}>}>}>  $columns
     */
    private function syncColumns(WarehouseRow $row, array $columns): void
    {
        foreach ($columns as $columnIndex => $columnPayload) {
            $column = $row->columns()->create([
                'name' => $columnPayload['name'],
                'sort_order' => $columnIndex + 1,
            ]);

            $this->syncFloors($column, $columnPayload['floors']);
        }
    }

    /**
     * @param  array<int, array{name: string, places: array<int, array{name: string}>}>  $floors
     */
    private function syncFloors(WarehouseColumn $column, array $floors): void
    {
        foreach ($floors as $floorIndex => $floorPayload) {
            $floor = $column->floors()->create([
                'name' => $floorPayload['name'],
                'sort_order' => $floorIndex + 1,
            ]);

            $this->syncPlaces($floor, $floorPayload['places']);
        }
    }

    /**
     * @param  array<int, array{name: string}>  $places
     */
    private function syncPlaces(WarehouseFloor $floor, array $places): void
    {
        foreach ($places as $placeIndex => $placePayload) {
            $floor->places()->create([
                'name' => $placePayload['name'],
                'sort_order' => $placeIndex + 1,
            ]);
        }
    }

    private function freshWarehouse(Warehouse $warehouse): Warehouse
    {
        return $warehouse->fresh([
            'creator:id,name,last_name',
            'updater:id,name,last_name',
            'rows.columns.floors.places',
        ]) ?? $warehouse;
    }
}
