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
use App\Support\PaginationData;
use App\Support\QrCodeResolver;
use App\Support\TsdQrScanManager;
use App\Support\WarehouseHierarchyManager;
use App\Support\WarehousePageData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
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

    public function index(WarehousePageData $warehousePageData): Response
    {
        return Inertia::render('warehouses/Index', $warehousePageData->index());
    }

    public function show(Warehouse $warehouse, WarehousePageData $warehousePageData): Response
    {
        $inventoryPerPage = $this->resolveInventoryPerPage(request());
        $inventoryQrCodes = $this->warehouseInventoryQuery($warehouse)
            ->paginate($inventoryPerPage, ['*'], 'items_page')
            ->withQueryString()
            ->through(fn (WarehouseItem $warehouseItem): array => (new ApiWarehouseItemResource($warehouseItem))->resolve());

        return Inertia::render('warehouses/Show', [
            ...$warehousePageData->show($warehouse),
            'inventoryQrCodes' => PaginationData::from($inventoryQrCodes),
            'inventoryPerPageOptions' => self::INVENTORY_PER_PAGE_OPTIONS,
            'filters' => [
                'items_per_page' => $inventoryPerPage,
            ],
        ]);
    }

    public function floor(
        Warehouse $warehouse,
        WarehouseFloor $warehouseFloor,
        WarehousePageData $warehousePageData,
    ): JsonResponse {
        return response()->json([
            'data' => $warehousePageData->floor($warehouse, $warehouseFloor),
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
            ->whereIn(
                'warehouse_place_id',
                WarehousePlace::query()
                    ->whereIn(
                        'warehouse_floor_id',
                        WarehouseFloor::query()
                            ->whereIn(
                                'warehouse_column_id',
                                WarehouseColumn::query()
                                    ->whereIn(
                                        'warehouse_row_id',
                                        $warehouse->rows()->select('id'),
                                    )
                                    ->select('id'),
                            )
                            ->select('id'),
                    )
                    ->select('id'),
            )
            ->orderBy('name')
            ->orderBy('id');
    }
}
