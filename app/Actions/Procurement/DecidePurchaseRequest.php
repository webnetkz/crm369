<?php

namespace App\Actions\Procurement;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DecidePurchaseRequest
{
    public function handle(
        PurchaseRequest $purchaseRequest,
        string $decision,
        ?string $rejectionReason,
        ?User $user,
    ): PurchaseRequest {
        return DB::transaction(function () use ($purchaseRequest, $decision, $rejectionReason, $user): PurchaseRequest {
            $lockedRequest = PurchaseRequest::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($purchaseRequest->id);

            if ($lockedRequest->status !== PurchaseRequest::STATUS_PENDING_APPROVAL) {
                throw ValidationException::withMessages([
                    'decision' => __('ui.procurement.validation.request_already_decided'),
                ]);
            }

            if ($decision === 'approve' && $lockedRequest->estimatedTotal() > (float) $lockedRequest->budget_amount) {
                throw ValidationException::withMessages([
                    'decision' => __('ui.procurement.validation.budget_below_estimate'),
                ]);
            }

            $lockedRequest->update($decision === 'approve'
                ? [
                    'status' => PurchaseRequest::STATUS_APPROVED,
                    'approved_by_user_id' => $user?->id,
                    'approved_at' => now(),
                    'rejection_reason' => null,
                ]
                : [
                    'status' => PurchaseRequest::STATUS_REJECTED,
                    'approved_by_user_id' => $user?->id,
                    'approved_at' => null,
                    'rejection_reason' => $rejectionReason,
                ]);

            return $lockedRequest->refresh();
        }, 3);
    }
}
