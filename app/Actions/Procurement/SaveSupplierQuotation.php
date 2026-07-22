<?php

namespace App\Actions\Procurement;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use App\Models\SupplierQuotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveSupplierQuotation
{
    /** @param array<string, mixed> $payload */
    public function handle(array $payload, ?User $user): SupplierQuotation
    {
        return DB::transaction(function () use ($payload, $user): SupplierQuotation {
            $requestItem = PurchaseRequestItem::query()
                ->lockForUpdate()
                ->findOrFail($payload['purchase_request_item_id']);
            $purchaseRequest = PurchaseRequest::query()
                ->lockForUpdate()
                ->findOrFail($requestItem->purchase_request_id);
            $supplier = Supplier::query()
                ->lockForUpdate()
                ->findOrFail($payload['supplier_id']);

            if (! in_array($purchaseRequest->status, [PurchaseRequest::STATUS_PENDING_APPROVAL, PurchaseRequest::STATUS_APPROVED], true)) {
                throw ValidationException::withMessages([
                    'purchase_request_item_id' => __('ui.procurement.validation.request_not_approved'),
                ]);
            }

            if (! $supplier->is_active) {
                throw ValidationException::withMessages([
                    'supplier_id' => __('ui.procurement.validation.supplier_inactive'),
                ]);
            }

            if ($payload['currency'] !== $purchaseRequest->currency) {
                throw ValidationException::withMessages([
                    'currency' => __('ui.procurement.validation.quote_currency_mismatch'),
                ]);
            }

            return SupplierQuotation::query()->updateOrCreate([
                'purchase_request_item_id' => $requestItem->id,
                'supplier_id' => $supplier->id,
            ], [
                ...$payload,
                'created_by_user_id' => $user?->id,
            ]);
        }, 3);
    }
}
