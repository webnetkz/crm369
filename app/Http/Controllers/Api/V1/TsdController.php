<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTsdQrScanRequest;
use App\Http\Resources\ApiResolvedQrCodeResource;
use App\Http\Resources\ApiTsdQrScanResource;
use App\Models\PortalSetting;
use App\Models\TsdQrScan;
use App\Models\User;
use App\Support\QrCodeResolver;
use App\Support\TsdQrScanManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TsdController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 25), 100));

        $scans = TsdQrScan::query()
            ->with(['scannedBy:id,name,last_name,email', 'portalWebhook:id,name'])
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->limit($perPage)
            ->get();

        return response()->json([
            'data' => ApiTsdQrScanResource::collection($scans)->resolve(),
            'meta' => [
                'per_page' => $perPage,
                'total' => TsdQrScan::query()->count(),
            ],
        ]);
    }

    public function store(
        StoreTsdQrScanRequest $request,
        TsdQrScanManager $scanManager,
        QrCodeResolver $qrCodeResolver,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $scan = $scanManager->create(
            payload: $request->scanPayload(),
            source: TsdQrScan::SOURCE_API,
            user: $user,
        );

        $scan->load(['scannedBy:id,name,last_name,email', 'portalWebhook:id,name']);

        return response()->json([
            'message' => __('ui.tsd.created_success'),
            'data' => (new ApiTsdQrScanResource($scan))->resolve(),
            'resolved' => $this->resolvedWarehouseQrCode($user, $scan->qr_code, $qrCodeResolver),
        ], 201);
    }

    /**
     * The warehouse position of the scanned QR code. Like the web scanner, it is only
     * resolved for users who can open the warehouses module.
     *
     * @return array<string, mixed>|null
     */
    private function resolvedWarehouseQrCode(User $user, string $qrCode, QrCodeResolver $qrCodeResolver): ?array
    {
        if (! PortalSetting::current()->isModuleEnabled('warehouses') || ! $user->canAccessWarehouses()) {
            return null;
        }

        $resolvedQrCode = $qrCodeResolver->resolve($qrCode);

        return $resolvedQrCode !== null
            ? (new ApiResolvedQrCodeResource($resolvedQrCode))->resolve()
            : null;
    }
}
