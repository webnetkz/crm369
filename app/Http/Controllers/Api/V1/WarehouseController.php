<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Http\Resources\ApiWarehouseItemResource;
use App\Http\Resources\ApiWarehouseResource;
use App\Models\Warehouse;
use App\Models\WarehouseItem;
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

    public function items(Request $request, Warehouse $warehouse): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 25), 100));

        $warehouseItems = WarehouseItem::query()
            ->with('place.floor.column.row.warehouse')
            ->whereHas('place.floor.column.row', fn ($query) => $query->where('warehouse_id', $warehouse->id))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => collect($warehouseItems->items())
                ->map(fn (WarehouseItem $warehouseItem): array => (new ApiWarehouseItemResource($warehouseItem))->resolve())
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $warehouseItems->currentPage(),
                'last_page' => $warehouseItems->lastPage(),
                'per_page' => $warehouseItems->perPage(),
                'total' => $warehouseItems->total(),
            ],
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
