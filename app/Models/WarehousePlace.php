<?php

namespace App\Models;

use App\Concerns\AssignsQrCode;
use Database\Factories\WarehousePlaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $warehouse_floor_id
 * @property string $name
 * @property string $qr_code
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['warehouse_floor_id', 'name', 'qr_code', 'sort_order'])]
class WarehousePlace extends Model
{
    /** @use HasFactory<WarehousePlaceFactory> */
    use AssignsQrCode;

    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'warehouse_floor_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected function qrCodePrefix(): string
    {
        return 'WP';
    }

    /**
     * @return BelongsTo<WarehouseFloor, $this>
     */
    public function floor(): BelongsTo
    {
        return $this->belongsTo(WarehouseFloor::class, 'warehouse_floor_id');
    }

    /**
     * @return HasMany<WarehouseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(WarehouseItem::class)
            ->orderBy('name')
            ->orderBy('id');
    }

    public function itemCount(): int
    {
        return $this->relationLoaded('items')
            ? $this->items->count()
            : $this->items()->count();
    }
}
