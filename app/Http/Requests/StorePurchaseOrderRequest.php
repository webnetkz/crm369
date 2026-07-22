<?php

namespace App\Http\Requests;

use App\Models\PortalWebhook;
use App\Models\PurchaseRequest;
use App\Models\SupplierQuotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->route('portalWebhook') instanceof PortalWebhook) {
            return true;
        }

        return $this->user()?->canManageProcurementOrders() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $notes = $this->input('notes');

        $this->merge([
            'notes' => is_string($notes) && trim($notes) !== '' ? trim($notes) : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'purchase_request_id' => ['required', 'integer', 'exists:purchase_requests,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'quotation_ids' => ['required', 'array', 'list', 'min:1', 'max:50'],
            'quotation_ids.*' => ['required', 'integer', 'distinct:strict', 'exists:supplier_quotations,id'],
            'ordered_at' => ['required', 'date'],
            'expected_at' => ['nullable', 'date', 'after_or_equal:ordered_at'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $purchaseRequest = PurchaseRequest::query()
                    ->withCount('items')
                    ->find($this->integer('purchase_request_id'));
                $quotations = SupplierQuotation::query()
                    ->whereKey($this->input('quotation_ids'))
                    ->get(['id', 'purchase_request_item_id', 'supplier_id']);

                if (! $purchaseRequest || ! in_array($purchaseRequest->status, [PurchaseRequest::STATUS_APPROVED, PurchaseRequest::STATUS_ORDERED], true)) {
                    $validator->errors()->add('purchase_request_id', __('ui.procurement.validation.request_not_approved'));

                    return;
                }

                $supplierId = $this->integer('supplier_id');
                $requestItemIds = $purchaseRequest->items()->pluck('id');

                if (
                    $quotations->count() !== $purchaseRequest->items_count
                    || $quotations->contains(fn (SupplierQuotation $quotation): bool => $quotation->supplier_id !== $supplierId)
                    || $quotations->pluck('purchase_request_item_id')->unique()->count() !== $purchaseRequest->items_count
                    || $quotations->pluck('purchase_request_item_id')->diff($requestItemIds)->isNotEmpty()
                ) {
                    $validator->errors()->add('quotation_ids', __('ui.procurement.validation.complete_supplier_quote_required'));
                }
            },
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->safe()->except('quotation_ids');
    }

    /** @return array<int, int> */
    public function quotationIds(): array
    {
        return array_map('intval', $this->validated('quotation_ids'));
    }
}
