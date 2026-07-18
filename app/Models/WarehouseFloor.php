<?php

namespace App\Models;

use App\Concerns\AssignsQrCode;
use Database\Factories\WarehouseFloorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $warehouse_column_id
 * @property string $name
 * @property string $qr_code
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['warehouse_column_id', 'name', 'qr_code', 'sort_order'])]
class WarehouseFloor extends Model
{
    use AssignsQrCode;

    /** @use HasFactory<WarehouseFloorFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'warehouse_column_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected function qrCodePrefix(): string
    {
        return 'WF';
    }

    /**
     * @return BelongsTo<WarehouseColumn, $this>
     */
    public function column(): BelongsTo
    {
        return $this->belongsTo(WarehouseColumn::class, 'warehouse_column_id');
    }

    /**
     * @return HasMany<WarehousePlace, $this>
     */
    public function places(): HasMany
    {
        return $this->hasMany(WarehousePlace::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return HasManyThrough<WarehouseItem, WarehousePlace, $this>
     */
    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(
            WarehouseItem::class,
            WarehousePlace::class,
            'warehouse_floor_id',
            'warehouse_place_id',
        );
    }

    public function placeCount(): int
    {
        return $this->relationLoaded('places')
            ? $this->places->count()
            : $this->places()->count();
    }

    public function itemCount(): int
    {
        if ($this->relationLoaded('places')) {
            return $this->places->sum(fn (WarehousePlace $place): int => $place->itemCount());
        }

        return WarehouseItem::query()
            ->whereIn('warehouse_place_id', $this->places()->select('id'))
            ->count();
    }
}
