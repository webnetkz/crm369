<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTsdQrScanRequest;
use App\Http\Resources\ApiTsdQrScanResource;
use App\Models\EquipmentItem;
use App\Models\TsdQrScan;
use App\Support\QrCodeResolver;
use App\Support\TsdQrScanManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TsdController extends Controller
{
    public function index(): Response
    {
        return $this->renderPage();
    }

    public function scan(Request $request): Response
    {
        return $this->renderPage(
            autoStartScanner: true,
            initialQrCode: $request->string('qr_code')->trim()->limit(2048, '')->value(),
        );
    }

    private function renderPage(bool $autoStartScanner = false, string $initialQrCode = ''): Response
    {
        return Inertia::render('tsd/Index', [
            'stats' => [
                'total' => TsdQrScan::query()->count(),
                'today' => TsdQrScan::query()->whereDate('scanned_at', today())->count(),
                'web' => TsdQrScan::query()->where('source', TsdQrScan::SOURCE_WEB)->count(),
                'api' => TsdQrScan::query()->where('source', TsdQrScan::SOURCE_API)->count(),
                'webhook' => TsdQrScan::query()->where('source', TsdQrScan::SOURCE_WEBHOOK)->count(),
            ],
            'autoStartScanner' => $autoStartScanner,
            'initialQrCode' => $initialQrCode,
            'recentScans' => ApiTsdQrScanResource::collection(
                TsdQrScan::query()
                    ->with(['scannedBy:id,name,last_name,email', 'portalWebhook:id,name'])
                    ->orderByDesc('scanned_at')
                    ->orderByDesc('id')
                    ->limit(20)
                    ->get()
            )->resolve(),
        ]);
    }

    public function store(
        StoreTsdQrScanRequest $request,
        TsdQrScanManager $scanManager,
        QrCodeResolver $qrCodeResolver,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $scan = $scanManager->create(
            payload: $request->scanPayload(),
            source: TsdQrScan::SOURCE_WEB,
            user: $user,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.tsd.created_success'),
        ]);

        if ($user->canAccessEquipment()) {
            $equipmentItem = EquipmentItem::query()
                ->matchingQrCode($scan->qr_code, $scan->normalized_qr_code)
                ->first();

            if ($equipmentItem instanceof EquipmentItem) {
                return to_route('equipment.index', ['equipment' => $equipmentItem->id]);
            }
        }

        if ($user->canAccessWarehouses()) {
            $resolvedQrCode = $qrCodeResolver->resolve($scan->qr_code);

            if ($resolvedQrCode !== null) {
                return to_route('warehouses.show', [
                    'warehouse' => $resolvedQrCode['warehouse']['id'],
                    'qr_code' => $resolvedQrCode['qr_code'],
                ]);
            }
        }

        return back();
    }
}
