<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTsdQrScanRequest;
use App\Http\Resources\ApiResolvedQrCodeResource;
use App\Http\Resources\ApiTsdQrScanResource;
use App\Models\TsdQrScan;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseFloor;
use App\Support\QrCodeResolver;
use App\Support\TsdQrScanManager;
use App\Support\WarehousePageData;
use Illuminate\Http\JsonResponse;

class MobileWarehouseController extends Controller
{
    /**
     * The places of a warehouse floor with the items stored on them (the web warehouse map floor data).
     */
    public function floor(
        Warehouse $warehouse,
        WarehouseFloor $warehouseFloor,
        WarehousePageData $warehousePageData,
    ): JsonResponse {
        return response()->json([
            'data' => $warehousePageData->floor($warehouse, $warehouseFloor),
        ]);
    }

    /**
     * Record a warehouse lookup scan and resolve the scanned QR code (the web warehouse scanner).
     *
     * An unknown QR code is not an error: the scan is still recorded and the resolved entity is null.
     */
    public function scan(
        StoreTsdQrScanRequest $request,
        TsdQrScanManager $tsdQrScanManager,
        QrCodeResolver $qrCodeResolver,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $scan = $tsdQrScanManager->create(
            payload: [
                ...$request->scanPayload(),
                'context' => $request->validated('context') ?? 'warehouse_lookup',
            ],
            source: TsdQrScan::SOURCE_API,
            user: $user,
        );

        $resolvedQrCode = $qrCodeResolver->resolve($request->validated('qr_code'));

        return response()->json([
            'message' => $resolvedQrCode !== null
                ? __('ui.warehouses.scan_found_success')
                : __('ui.warehouses.scan_not_found'),
            'data' => $resolvedQrCode !== null
                ? (new ApiResolvedQrCodeResource($resolvedQrCode))->resolve()
                : null,
            'scan' => (new ApiTsdQrScanResource(
                $scan->load(['scannedBy:id,name,last_name,email', 'portalWebhook:id,name']),
            ))->resolve(),
        ]);
    }
}
