<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Http\Resources\ApiWarehouseResource;
use App\Models\Warehouse;
use App\Support\WarehouseHierarchyManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function __construct(private WarehouseHierarchyManager $warehouseHierarchyManager) {}

    public function index(): JsonResponse
    {
        $warehouses = Warehouse::query()
            ->with([
                'creator:id,name,last_name',
                'updater:id,name,last_name',
                'rows.columns.floors.places',
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $warehouses
                ->map(fn (Warehouse $warehouse): array => (new ApiWarehouseResource($warehouse))->resolve())
                ->values()
                ->all(),
            'meta' => [
                'total' => $warehouses->count(),
                'total_area_sqm' => round($warehouses->sum('area_sqm'), 2),
                'row_count' => $warehouses->sum(fn (Warehouse $warehouse): int => $warehouse->rowCount()),
                'column_count' => $warehouses->sum(fn (Warehouse $warehouse): int => $warehouse->columnCount()),
                'floor_count' => $warehouses->sum(fn (Warehouse $warehouse): int => $warehouse->floorCount()),
                'place_count' => $warehouses->sum(fn (Warehouse $warehouse): int => $warehouse->placeCount()),
            ],
        ]);
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        $warehouse->load([
            'creator:id,name,last_name',
            'updater:id,name,last_name',
            'rows.columns.floors.places',
        ]);

        return response()->json([
            'data' => (new ApiWarehouseResource($warehouse))->resolve(),
        ]);
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $warehouse = $this->warehouseHierarchyManager->create(
            $request->payload(),
            $request->user(),
        );

        return response()->json([
            'message' => __('ui.warehouses.created_success'),
            'data' => (new ApiWarehouseResource($warehouse))->resolve(),
        ], 201);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $warehouse = $this->warehouseHierarchyManager->update(
            $warehouse,
            $request->payload(),
            $request->user(),
        );

        return response()->json([
            'message' => __('ui.warehouses.updated_success'),
            'data' => (new ApiWarehouseResource($warehouse))->resolve(),
        ]);
    }

    public function destroy(Request $request, Warehouse $warehouse): JsonResponse
    {
        $deletedId = $warehouse->id;
        $warehouse->delete();

        return response()->json([
            'message' => __('ui.warehouses.deleted_success'),
            'data' => [
                'id' => $deletedId,
            ],
        ]);
    }
}
