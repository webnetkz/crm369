<?php

namespace App\Models;

use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number',
    'purchase_request_id',
    'supplier_id',
    'status',
    'currency',
    'ordered_at',
    'expected_at',
    'sent_at',
    'subtotal',
    'tax_amount',
    'delivery_amount',
    'total_amount',
    'notes',
    'created_by_user_id',
    'sent_by_user_id',
])]
class PurchaseOrder extends Model
{
    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_SENT = 'sent';

    public const string STATUS_PARTIALLY_RECEIVED = 'partially_received';

    public const string STATUS_RECEIVED = 'received';

    public const string STATUS_CANCELLED = 'cancelled';

    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'purchase_request_id' => 'integer',
            'supplier_id' => 'integer',
            'ordered_at' => 'date',
            'expected_at' => 'date',
            'sent_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'delivery_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'created_by_user_id' => 'integer',
            'sent_by_user_id' => 'integer',
        ];
    }

    /** @return BelongsTo<PurchaseRequest, $this> */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
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

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /** @return HasMany<PurchaseOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /** @return HasMany<GoodsReceipt, $this> */
    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    /** @return HasMany<PurchaseReturn, $this> */
    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }
}
