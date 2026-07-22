<?php

namespace App\Models;

use Database\Factories\SupplierQuotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_request_item_id',
    'supplier_id',
    'unit_price',
    'currency',
    'tax_percent',
    'delivery_cost',
    'quoted_at',
    'valid_until',
    'lead_time_days',
    'notes',
    'created_by_user_id',
])]
class SupplierQuotation extends Model
{
    /** @use HasFactory<SupplierQuotationFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'purchase_request_item_id' => 'integer',
            'supplier_id' => 'integer',
            'unit_price' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'delivery_cost' => 'decimal:2',
            'quoted_at' => 'date',
            'valid_until' => 'date',
            'lead_time_days' => 'integer',
            'created_by_user_id' => 'integer',
        ];
    }

    /** @return BelongsTo<PurchaseRequestItem, $this> */
    public function purchaseRequestItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function landedTotal(): float
    {
        $quantity = max(1, $this->purchaseRequestItem?->quantity ?? 1);
        $subtotal = (float) $this->unit_price * $quantity;
        $tax = $subtotal * ((float) $this->tax_percent / 100);

        return round($subtotal + $tax + (float) $this->delivery_cost, 2);
    }

    public function landedUnitPrice(): float
    {
        $quantity = max(1, $this->purchaseRequestItem?->quantity ?? 1);

        return round($this->landedTotal() / $quantity, 2);
    }
}
