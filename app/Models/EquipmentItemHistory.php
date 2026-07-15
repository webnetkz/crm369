<?php

namespace App\Models;

use Database\Factories\EquipmentItemHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $equipment_item_id
 * @property string $event_type
 * @property string $source
 * @property int|null $actor_user_id
 * @property array<string, array{from: mixed, to: mixed}>|null $changes
 * @property array{name: string, qr_code: string, status: string, responsible_user: array<string, mixed>|null, issued_to_user: array<string, mixed>|null} $snapshot
 * @property Carbon|null $changed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'equipment_item_id',
    'event_type',
    'source',
    'actor_user_id',
    'changes',
    'snapshot',
    'changed_at',
])]
class EquipmentItemHistory extends Model
{
    public const string EVENT_CREATED = 'created';

    public const string EVENT_UPDATED = 'updated';

    public const string SOURCE_WEB = 'web';

    public const string SOURCE_API = 'api';

    public const string SOURCE_WEBHOOK = 'webhook';

    public const string SOURCE_CSV = 'csv';

    /** @use HasFactory<EquipmentItemHistoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'equipment_item_id' => 'integer',
            'actor_user_id' => 'integer',
            'changes' => 'array',
            'snapshot' => 'array',
            'changed_at' => 'datetime',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function availableEventTypes(): array
    {
        return [
            self::EVENT_CREATED,
            self::EVENT_UPDATED,
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
            self::SOURCE_CSV,
        ];
    }

    public function eventTypeLabel(): string
    {
        return match ($this->event_type) {
            self::EVENT_UPDATED => __('ui.equipment.history_event_updated'),
            default => __('ui.equipment.history_event_created'),
        };
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_API => __('ui.equipment.history_source_api'),
            self::SOURCE_WEBHOOK => __('ui.equipment.history_source_webhook'),
            self::SOURCE_CSV => __('ui.equipment.history_source_csv'),
            default => __('ui.equipment.history_source_web'),
        };
    }

    /**
     * @return BelongsTo<EquipmentItem, $this>
     */
    public function equipmentItem(): BelongsTo
    {
        return $this->belongsTo(EquipmentItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
