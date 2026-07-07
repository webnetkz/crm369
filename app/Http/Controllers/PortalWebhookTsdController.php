<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTsdQrScanRequest;
use App\Http\Resources\ApiResolvedQrCodeResource;
use App\Http\Resources\ApiTsdQrScanResource;
use App\Models\PortalWebhook;
use App\Models\TsdQrScan;
use App\Support\QrCodeResolver;
use App\Support\TsdQrScanManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalWebhookTsdController extends Controller
{
    public function index(Request $request, PortalWebhook $portalWebhook): JsonResponse
    {
        /** @var PortalWebhook $resolvedWebhook */
        $resolvedWebhook = $request->attributes->get('portal_webhook', $portalWebhook);
        $perPage = max(1, min((int) $request->integer('per_page', 25), 100));

        $scans = TsdQrScan::query()
            ->with(['scannedBy:id,name,last_name,email', 'portalWebhook:id,name'])
            ->where('portal_webhook_id', $resolvedWebhook->id)
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->limit($perPage)
            ->get();

        return response()->json([
            'webhook' => [
                'id' => $resolvedWebhook->id,
                'name' => $resolvedWebhook->name,
            ],
            'data' => ApiTsdQrScanResource::collection($scans)->resolve(),
            'meta' => [
                'per_page' => $perPage,
                'total' => TsdQrScan::query()->where('portal_webhook_id', $resolvedWebhook->id)->count(),
            ],
        ]);
    }

    public function store(
        StoreTsdQrScanRequest $request,
        PortalWebhook $portalWebhook,
        TsdQrScanManager $scanManager,
        QrCodeResolver $qrCodeResolver,
    ): JsonResponse {
        /** @var PortalWebhook $resolvedWebhook */
        $resolvedWebhook = $request->attributes->get('portal_webhook', $portalWebhook);

        $scan = $scanManager->create(
            payload: $request->scanPayload(),
            source: TsdQrScan::SOURCE_WEBHOOK,
            portalWebhook: $resolvedWebhook,
        );

        $scan->load(['scannedBy:id,name,last_name,email', 'portalWebhook:id,name']);

        return response()->json([
            'message' => __('ui.tsd.created_success'),
            'data' => (new ApiTsdQrScanResource($scan))->resolve(),
            'resolved' => ($resolvedQrCode = $qrCodeResolver->resolve($scan->qr_code)) !== null
                ? (new ApiResolvedQrCodeResource($resolvedQrCode))->resolve()
                : null,
        ], 201);
    }
}
