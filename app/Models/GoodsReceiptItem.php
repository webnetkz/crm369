<?php

namespace App\Models;

use Database\Factories\GoodsReceiptItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'goods_receipt_id',
    'purchase_order_item_id',
    'warehouse_item_id',
    'warehouse_place_id',
    'quantity',
    'unit_price',
    'line_total',
])]
class GoodsReceiptItem extends Model
{
    /** @use HasFactory<GoodsReceiptItemFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'goods_receipt_id' => 'integer',
            'purchase_order_item_id' => 'integer',
            'warehouse_item_id' => 'integer',
            'warehouse_place_id' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<GoodsReceipt, $this> */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    /** @return BelongsTo<PurchaseOrderItem, $this> */
    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    /** @return BelongsTo<WarehouseItem, $this> */
    public function warehouseItem(): BelongsTo
    {
        return $this->belongsTo(WarehouseItem::class);
    }

    /** @return BelongsTo<WarehousePlace, $this> */
    public function warehousePlace(): BelongsTo
    {
        return $this->belongsTo(WarehousePlace::class);
    }

    /** @return HasMany<PurchaseReturnItem, $this> */
    public function purchaseReturnItems(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function returnedQuantity(): int
    {
        if ($this->relationLoaded('purchaseReturnItems')) {
            return (int) $this->purchaseReturnItems->sum('quantity');
        }

        return (int) $this->purchaseReturnItems()->sum('quantity');
    }

    public function returnableQuantity(): int
    {
        return max(0, $this->quantity - $this->returnedQuantity());
    }
}
