<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTsdQrScanRequest;
use App\Http\Resources\ApiTsdQrScanResource;
use App\Models\TsdQrScan;
use App\Support\TsdQrScanManager;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TsdController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('tsd/Index', [
            'stats' => [
                'total' => TsdQrScan::query()->count(),
                'today' => TsdQrScan::query()->whereDate('scanned_at', today())->count(),
                'web' => TsdQrScan::query()->where('source', TsdQrScan::SOURCE_WEB)->count(),
                'api' => TsdQrScan::query()->where('source', TsdQrScan::SOURCE_API)->count(),
                'webhook' => TsdQrScan::query()->where('source', TsdQrScan::SOURCE_WEBHOOK)->count(),
            ],
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

    public function store(StoreTsdQrScanRequest $request, TsdQrScanManager $scanManager): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $scanManager->create(
            payload: $request->scanPayload(),
            source: TsdQrScan::SOURCE_WEB,
            user: $user,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.tsd.created_success'),
        ]);

        return back();
    }
}
