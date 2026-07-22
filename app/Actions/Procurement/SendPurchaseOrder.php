<?php

namespace App\Actions\Procurement;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SendPurchaseOrder
{
    public function handle(PurchaseOrder $purchaseOrder, ?User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $user): PurchaseOrder {
            $lockedOrder = PurchaseOrder::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);

            if ($lockedOrder->status !== PurchaseOrder::STATUS_DRAFT || $lockedOrder->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'purchase_order' => __('ui.procurement.validation.order_cannot_be_sent'),
                ]);
            }

            $lockedOrder->update([
                'status' => PurchaseOrder::STATUS_SENT,
                'sent_at' => now(),
                'sent_by_user_id' => $user?->id,
            ]);

            return $lockedOrder->refresh();
        }, 3);
    }
}
