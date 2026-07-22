<?php

namespace App\Models;

use Database\Factories\PurchaseRequestItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'purchase_request_id',
    'warehouse_place_id',
    'warehouse_item_id',
    'item_name',
    'sku',
    'unit',
    'quantity',
    'target_unit_price',
    'production_reference',
    'notes',
])]
class PurchaseRequestItem extends Model
{
    /** @use HasFactory<PurchaseRequestItemFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'purchase_request_id' => 'integer',
            'warehouse_place_id' => 'integer',
            'warehouse_item_id' => 'integer',
            'quantity' => 'integer',
            'target_unit_price' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<PurchaseRequest, $this> */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
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

    /** @return HasMany<SupplierQuotation, $this> */
    public function quotations(): HasMany
    {
        return $this->hasMany(SupplierQuotation::class);
    }

    /** @return HasMany<PurchaseOrderItem, $this> */
    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
