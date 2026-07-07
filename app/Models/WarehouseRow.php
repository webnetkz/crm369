<?php

namespace App\Models;

use App\Concerns\AssignsQrCode;
use Database\Factories\WarehouseRowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $warehouse_id
 * @property string $name
 * @property string $qr_code
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['warehouse_id', 'name', 'qr_code', 'sort_order'])]
class WarehouseRow extends Model
{
    /** @use HasFactory<WarehouseRowFactory> */
    use AssignsQrCode;

    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'warehouse_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected function qrCodePrefix(): string
    {
        return 'WR';
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<WarehouseColumn, $this>
     */
    public function columns(): HasMany
    {
        return $this->hasMany(WarehouseColumn::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function columnCount(): int
    {
        return $this->relationLoaded('columns')
            ? $this->columns->count()
            : $this->columns()->count();
    }

    public function floorCount(): int
    {
        if ($this->relationLoaded('columns')) {
            return $this->columns->sum(fn (WarehouseColumn $column): int => $column->floorCount());
        }

        return WarehouseFloor::query()
            ->whereIn('warehouse_column_id', $this->columns()->select('id'))
            ->count();
    }

    public function placeCount(): int
    {
        if ($this->relationLoaded('columns')) {
            return $this->columns->sum(fn (WarehouseColumn $column): int => $column->placeCount());
        }

        return WarehousePlace::query()
            ->whereIn('warehouse_floor_id', WarehouseFloor::query()
                ->whereIn('warehouse_column_id', $this->columns()->select('id'))
                ->select('id'))
            ->count();
    }
}
