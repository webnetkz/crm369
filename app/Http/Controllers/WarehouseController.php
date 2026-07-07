<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Resources\ApiWarehouseResource;
use App\Models\Warehouse;
use App\Support\WarehouseHierarchyManager;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    public function index(): Response
    {
        $warehouses = Warehouse::query()
            ->with([
                'creator:id,name,last_name',
                'updater:id,name,last_name',
                'rows.columns.floors.places',
            ])
            ->orderBy('name')
            ->get();

        $warehouseData = $warehouses
            ->map(fn (Warehouse $warehouse): array => (new ApiWarehouseResource($warehouse))->resolve())
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

    public function store(
        StoreWarehouseRequest $request,
        WarehouseHierarchyManager $warehouseHierarchyManager,
    ): RedirectResponse {
        $warehouseHierarchyManager->create(
            $request->payload(),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.warehouses.created_success')]);

        return back();
    }
}
