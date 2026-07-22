<?php

namespace App\Actions\Procurement;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\SupplierQuotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatePurchaseOrder
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, int>  $quotationIds
     */
    public function handle(array $payload, array $quotationIds, ?User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($payload, $quotationIds, $user): PurchaseOrder {
            $purchaseRequest = PurchaseRequest::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($payload['purchase_request_id']);
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($payload['supplier_id']);

            if (! $supplier->is_active) {
                throw ValidationException::withMessages([
                    'supplier_id' => __('ui.procurement.validation.supplier_inactive'),
                ]);
            }

            if (! in_array($purchaseRequest->status, [PurchaseRequest::STATUS_APPROVED, PurchaseRequest::STATUS_ORDERED], true)) {
                throw ValidationException::withMessages([
                    'purchase_request_id' => __('ui.procurement.validation.request_not_approved'),
                ]);
            }

            if ($purchaseRequest->purchaseOrders()->exists()) {
                throw ValidationException::withMessages([
                    'purchase_request_id' => __('ui.procurement.validation.request_already_ordered'),
                ]);
            }

            $quotations = SupplierQuotation::query()
                ->with('purchaseRequestItem')
                ->whereKey($quotationIds)
                ->lockForUpdate()
                ->get();

            if ($quotations->contains(fn (SupplierQuotation $quotation): bool => $quotation->currency !== $purchaseRequest->currency)) {
                throw ValidationException::withMessages([
                    'quotation_ids' => __('ui.procurement.validation.quote_currency_mismatch'),
                ]);
            }

            $subtotal = 0.0;
            $taxAmount = 0.0;
            $deliveryAmount = 0.0;
            $itemPayloads = [];

            foreach ($quotations as $quotation) {
                $requestItem = $quotation->purchaseRequestItem;
                $lineSubtotal = (float) $quotation->unit_price * $requestItem->quantity;
                $lineTax = $lineSubtotal * ((float) $quotation->tax_percent / 100);
                $lineDelivery = (float) $quotation->delivery_cost;

                $subtotal += $lineSubtotal;
                $taxAmount += $lineTax;
                $deliveryAmount += $lineDelivery;
                $itemPayloads[] = [
                    'purchase_request_item_id' => $requestItem->id,
                    'supplier_quotation_id' => $quotation->id,
                    'warehouse_place_id' => $requestItem->warehouse_place_id,
                    'warehouse_item_id' => $requestItem->warehouse_item_id,
                    'item_name' => $requestItem->item_name,
                    'sku' => $requestItem->sku,
                    'unit' => $requestItem->unit,
                    'quantity' => $requestItem->quantity,
                    'received_quantity' => 0,
                    'returned_quantity' => 0,
                    'unit_price' => $quotation->unit_price,
                    'tax_percent' => $quotation->tax_percent,
                    'line_total' => round($lineSubtotal + $lineTax + $lineDelivery, 2),
                    'notes' => $quotation->notes,
                ];
            }

            $totalAmount = round($subtotal + $taxAmount + $deliveryAmount, 2);

            if ($totalAmount > (float) $purchaseRequest->budget_amount) {
                throw ValidationException::withMessages([
                    'quotation_ids' => __('ui.procurement.validation.order_exceeds_budget'),
                ]);
            }

            $purchaseOrder = PurchaseOrder::query()->create([
                ...$payload,
                'number' => $this->number(),
                'status' => PurchaseOrder::STATUS_DRAFT,
                'currency' => $purchaseRequest->currency,
                'subtotal' => round($subtotal, 2),
                'tax_amount' => round($taxAmount, 2),
                'delivery_amount' => round($deliveryAmount, 2),
                'total_amount' => $totalAmount,
                'created_by_user_id' => $user?->id,
            ]);

            $purchaseOrder->items()->createMany($itemPayloads);
            $purchaseRequest->update([
                'status' => PurchaseRequest::STATUS_ORDERED,
                'ordered_at' => now(),
            ]);

            return $purchaseOrder->load(['supplier', 'items']);
        }, 3);
    }

    private function number(): string
    {
        return 'PO-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
    }
}
