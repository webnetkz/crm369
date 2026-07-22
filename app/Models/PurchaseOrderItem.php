<?php

namespace App\Models;

use Database\Factories\PurchaseOrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'purchase_order_id',
    'purchase_request_item_id',
    'supplier_quotation_id',
    'warehouse_place_id',
    'warehouse_item_id',
    'item_name',
    'sku',
    'unit',
    'quantity',
    'received_quantity',
    'returned_quantity',
    'unit_price',
    'tax_percent',
    'line_total',
    'notes',
])]
class PurchaseOrderItem extends Model
{
    /** @use HasFactory<PurchaseOrderItemFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'purchase_order_id' => 'integer',
            'purchase_request_item_id' => 'integer',
            'supplier_quotation_id' => 'integer',
            'warehouse_place_id' => 'integer',
            'warehouse_item_id' => 'integer',
            'quantity' => 'integer',
            'received_quantity' => 'integer',
            'returned_quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<PurchaseRequestItem, $this> */
    public function purchaseRequestItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class);
    }

    /** @return BelongsTo<SupplierQuotation, $this> */
    public function supplierQuotation(): BelongsTo
    {
        return $this->belongsTo(SupplierQuotation::class);
    }

    /** @return BelongsTo<WarehousePlace, $this> */
    public function warehousePlace(): BelongsTo
    {
        return $this->belongsTo(WarehousePlace::class);
    }

    /** @return BelongsTo<WarehouseItem, $this> */
    public function warehouseItem(): BelongsTo
    {
        return $this->belongsTo(WarehouseItem::class);
    }

    /** @return HasMany<GoodsReceiptItem, $this> */
    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    /** @return HasMany<PurchaseReturnItem, $this> */
    public function purchaseReturnItems(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function remainingQuantity(): int
    {
        return max(0, $this->quantity - $this->received_quantity);
    }

    public function returnableQuantity(): int
    {
        return max(0, $this->received_quantity - $this->returned_quantity);
    }
}
