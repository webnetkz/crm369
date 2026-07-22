<?php

namespace App\Http\Requests;

use App\Models\PortalWebhook;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->route('portalWebhook') instanceof PortalWebhook) {
            return true;
        }

        return $this->user()?->canManageProcurement() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $notes = $this->input('notes');

        $this->merge([
            'currency' => strtoupper((string) $this->input('currency', 'KZT')),
            'notes' => is_string($notes) && trim($notes) !== '' ? trim($notes) : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'purchase_request_item_id' => ['required', 'integer', 'exists:purchase_request_items,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['required', 'string', Rule::in(['KZT', 'USD', 'EUR', 'RUB'])],
            'tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'delivery_cost' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'quoted_at' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:quoted_at'],
            'lead_time_days' => ['required', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->validated();
    }
}
