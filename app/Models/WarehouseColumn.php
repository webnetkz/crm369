<?php

namespace App\Models;

use App\Concerns\AssignsQrCode;
use Database\Factories\WarehouseColumnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $warehouse_row_id
 * @property string $name
 * @property string $qr_code
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['warehouse_row_id', 'name', 'qr_code', 'sort_order'])]
class WarehouseColumn extends Model
{
    /** @use HasFactory<WarehouseColumnFactory> */
    use AssignsQrCode;

    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'warehouse_row_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected function qrCodePrefix(): string
    {
        return 'WC';
    }

    /**
     * @return BelongsTo<WarehouseRow, $this>
     */
    public function row(): BelongsTo
    {
        return $this->belongsTo(WarehouseRow::class, 'warehouse_row_id');
    }

    /**
     * @return HasMany<WarehouseFloor, $this>
     */
    public function floors(): HasMany
    {
        return $this->hasMany(WarehouseFloor::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function floorCount(): int
    {
        return $this->relationLoaded('floors')
            ? $this->floors->count()
            : $this->floors()->count();
    }

    public function placeCount(): int
    {
        if ($this->relationLoaded('floors')) {
            return $this->floors->sum(fn (WarehouseFloor $floor): int => $floor->placeCount());
        }

        return WarehousePlace::query()
            ->whereIn('warehouse_floor_id', $this->floors()->select('id'))
            ->count();
    }

    public function itemCount(): int
    {
        if ($this->relationLoaded('floors')) {
            return $this->floors->sum(fn (WarehouseFloor $floor): int => $floor->itemCount());
        }

        return WarehouseItem::query()
            ->whereIn('warehouse_place_id', WarehousePlace::query()
                ->whereIn('warehouse_floor_id', $this->floors()->select('id'))
                ->select('id'))
            ->count();
    }
}
