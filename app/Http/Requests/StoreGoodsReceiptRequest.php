<?php

namespace App\Http\Requests;

use App\Models\PortalWebhook;
use Illuminate\Foundation\Http\FormRequest;

class StoreGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->route('portalWebhook') instanceof PortalWebhook) {
            return true;
        }

        return $this->user()?->canReceiveProcurementOrders() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'external_reference' => $this->normalizeNullableString($this->input('external_reference')),
            'notes' => $this->normalizeNullableString($this->input('notes')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'received_at' => ['required', 'date'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'items' => ['required', 'array', 'list', 'min:1', 'max:50'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'distinct:strict', 'exists:purchase_order_items,id'],
            'items.*.warehouse_place_id' => ['required', 'integer', 'exists:warehouse_places,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000000'],
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->safe()->except('items');
    }

    /** @return array<int, array<string, int>> */
    public function itemPayloads(): array
    {
        return $this->validated('items');
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
