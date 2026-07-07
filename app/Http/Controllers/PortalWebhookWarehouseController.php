<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Http\Resources\ApiWarehouseResource;
use App\Models\PortalWebhook;
use App\Models\Warehouse;
use App\Support\WarehouseHierarchyManager;
use Illuminate\Http\JsonResponse;

class PortalWebhookWarehouseController extends Controller
{
    public function __construct(private WarehouseHierarchyManager $warehouseHierarchyManager) {}

    public function index(PortalWebhook $portalWebhook): JsonResponse
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
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'data' => $warehouses
                ->map(fn (Warehouse $warehouse): array => (new ApiWarehouseResource($warehouse))->resolve())
                ->values()
                ->all(),
            'meta' => [
                'total' => $warehouses->count(),
            ],
        ]);
    }

    public function show(PortalWebhook $portalWebhook, Warehouse $warehouse): JsonResponse
    {
        $warehouse->load([
            'creator:id,name,last_name',
            'updater:id,name,last_name',
            'rows.columns.floors.places',
        ]);

        return response()->json([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'data' => (new ApiWarehouseResource($warehouse))->resolve(),
        ]);
    }

    public function store(StoreWarehouseRequest $request, PortalWebhook $portalWebhook): JsonResponse
    {
        $warehouse = $this->warehouseHierarchyManager->create($request->payload());

        return response()->json([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'message' => __('ui.warehouses.created_success'),
            'data' => (new ApiWarehouseResource($warehouse))->resolve(),
        ], 201);
    }

    public function update(UpdateWarehouseRequest $request, PortalWebhook $portalWebhook, Warehouse $warehouse): JsonResponse
    {
        $warehouse = $this->warehouseHierarchyManager->update($warehouse, $request->payload());

        return response()->json([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'message' => __('ui.warehouses.updated_success'),
            'data' => (new ApiWarehouseResource($warehouse))->resolve(),
        ]);
    }

    public function destroy(PortalWebhook $portalWebhook, Warehouse $warehouse): JsonResponse
    {
        $deletedId = $warehouse->id;
        $warehouse->delete();

        return response()->json([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'message' => __('ui.warehouses.deleted_success'),
            'data' => [
                'id' => $deletedId,
            ],
        ]);
    }
}
