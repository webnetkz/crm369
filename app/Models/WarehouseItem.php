<?php

namespace App\Models;

use App\Concerns\AssignsQrCode;
use Database\Factories\WarehouseItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $warehouse_place_id
 * @property string $name
 * @property string|null $sku
 * @property string $qr_code
 * @property int $quantity
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'warehouse_place_id',
    'name',
    'sku',
    'qr_code',
    'quantity',
    'notes',
])]
class WarehouseItem extends Model
{
    /** @use HasFactory<WarehouseItemFactory> */
    use AssignsQrCode;

    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'warehouse_place_id' => 'integer',
            'quantity' => 'integer',
        ];
    }

    protected function qrCodePrefix(): string
    {
        return 'WI';
    }

    /**
     * @return BelongsTo<WarehousePlace, $this>
     */
    public function place(): BelongsTo
    {
        return $this->belongsTo(WarehousePlace::class, 'warehouse_place_id');
    }
}
