<?php

namespace App\Support;

use App\Models\PortalWebhook;
use App\Models\TsdQrScan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TsdQrScanManager
{
    /**
     * @param  array{
     *     qr_code: string,
     *     device_name: ?string,
     *     location: ?string,
     *     context: ?string,
     *     payload: array<string, mixed>|null,
     *     scanned_at: string|null
     * }  $payload
     */
    public function create(
        array $payload,
        string $source,
        ?User $user = null,
        ?PortalWebhook $portalWebhook = null,
    ): TsdQrScan {
        if (! in_array($source, TsdQrScan::availableSources(), true)) {
            throw new InvalidArgumentException('Unsupported TSD scan source.');
        }

        $qrCode = trim($payload['qr_code']);

        return TsdQrScan::query()->create([
            'qr_code' => $qrCode,
            'normalized_qr_code' => $this->normalizeQrCode($qrCode),
            'source' => $source,
            'device_name' => $payload['device_name'],
            'location' => $payload['location'],
            'context' => $payload['context'],
            'payload' => $payload['payload'],
            'scanned_at' => $this->resolveScannedAt($payload['scanned_at'] ?? null),
            'scanned_by_user_id' => $user?->id,
            'portal_webhook_id' => $portalWebhook?->id,
        ]);
    }

    public function normalizeQrCode(string $qrCode): string
    {
        return Str::of($qrCode)
            ->replaceMatches('/\s+/', '')
            ->upper()
            ->value();
    }

    private function resolveScannedAt(?string $scannedAt): CarbonInterface
    {
        if (! is_string($scannedAt) || trim($scannedAt) === '') {
            return now();
        }

        return now()->parse($scannedAt);
    }
}
