<?php

namespace App\Models;

use Database\Factories\TsdQrScanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $qr_code
 * @property string $normalized_qr_code
 * @property string $source
 * @property string|null $device_name
 * @property string|null $location
 * @property string|null $context
 * @property array<string, mixed>|null $payload
 * @property Carbon $scanned_at
 * @property int|null $scanned_by_user_id
 * @property int|null $portal_webhook_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'qr_code',
    'normalized_qr_code',
    'source',
    'device_name',
    'location',
    'context',
    'payload',
    'scanned_at',
    'scanned_by_user_id',
    'portal_webhook_id',
])]
class TsdQrScan extends Model
{
    public const string SOURCE_WEB = 'web';

    public const string SOURCE_API = 'api';

    public const string SOURCE_WEBHOOK = 'webhook';

    /** @use HasFactory<TsdQrScanFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'scanned_at' => 'datetime',
            'scanned_by_user_id' => 'integer',
            'portal_webhook_id' => 'integer',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function availableSources(): array
    {
        return [
            self::SOURCE_WEB,
            self::SOURCE_API,
            self::SOURCE_WEBHOOK,
        ];
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_API => __('ui.tsd.source_api'),
            self::SOURCE_WEBHOOK => __('ui.tsd.source_webhook'),
            default => __('ui.tsd.source_web'),
        };
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by_user_id');
    }

    /**
     * @return BelongsTo<PortalWebhook, $this>
     */
    public function portalWebhook(): BelongsTo
    {
        return $this->belongsTo(PortalWebhook::class);
    }
}
