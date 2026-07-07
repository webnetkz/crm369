<?php

namespace App\Models;

use App\Concerns\AssignsQrCode;
use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property float $area_sqm
 * @property string $qr_code
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'area_sqm', 'qr_code', 'created_by_user_id', 'updated_by_user_id'])]
class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use AssignsQrCode;

    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'area_sqm' => 'float',
            'created_by_user_id' => 'integer',
            'updated_by_user_id' => 'integer',
        ];
    }

    protected function qrCodePrefix(): string
    {
        return 'WH';
    }

    /**
     * @return HasMany<WarehouseRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(WarehouseRow::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function rowCount(): int
    {
        return $this->relationLoaded('rows')
            ? $this->rows->count()
            : $this->rows()->count();
    }

    public function columnCount(): int
    {
        if ($this->relationLoaded('rows')) {
            return $this->rows->sum(fn (WarehouseRow $row): int => $row->columnCount());
        }

        return WarehouseColumn::query()
            ->whereIn('warehouse_row_id', $this->rows()->select('id'))
            ->count();
    }

    public function floorCount(): int
    {
        if ($this->relationLoaded('rows')) {
            return $this->rows->sum(fn (WarehouseRow $row): int => $row->floorCount());
        }

        return WarehouseFloor::query()
            ->whereIn('warehouse_column_id', WarehouseColumn::query()
                ->whereIn('warehouse_row_id', $this->rows()->select('id'))
                ->select('id'))
            ->count();
    }

    public function placeCount(): int
    {
        if ($this->relationLoaded('rows')) {
            return $this->rows->sum(fn (WarehouseRow $row): int => $row->placeCount());
        }

        return WarehousePlace::query()
            ->whereIn('warehouse_floor_id', WarehouseFloor::query()
                ->whereIn('warehouse_column_id', WarehouseColumn::query()
                    ->whereIn('warehouse_row_id', $this->rows()->select('id'))
                    ->select('id'))
                ->select('id'))
            ->count();
    }
}
