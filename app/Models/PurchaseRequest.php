<?php

namespace App\Models;

use Database\Factories\PurchaseRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number',
    'title',
    'status',
    'needed_at',
    'budget_amount',
    'currency',
    'justification',
    'rejection_reason',
    'requested_by_user_id',
    'approved_by_user_id',
    'submitted_at',
    'approved_at',
    'ordered_at',
])]
class PurchaseRequest extends Model
{
    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_PENDING_APPROVAL = 'pending_approval';

    public const string STATUS_APPROVED = 'approved';

    public const string STATUS_REJECTED = 'rejected';

    public const string STATUS_ORDERED = 'ordered';

    public const string STATUS_PARTIALLY_RECEIVED = 'partially_received';

    public const string STATUS_RECEIVED = 'received';

    /** @use HasFactory<PurchaseRequestFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'needed_at' => 'date',
            'budget_amount' => 'decimal:2',
            'requested_by_user_id' => 'integer',
            'approved_by_user_id' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'ordered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /** @return HasMany<PurchaseRequestItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    /** @return HasMany<PurchaseOrder, $this> */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function estimatedTotal(): float
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return round((float) $items->sum(
            fn (PurchaseRequestItem $item): float => $item->quantity * (float) $item->target_unit_price,
        ), 2);
    }

    /** @return array<int, string> */
    public static function availableStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_ORDERED,
            self::STATUS_PARTIALLY_RECEIVED,
            self::STATUS_RECEIVED,
        ];
    }
}
