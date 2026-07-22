<?php

namespace App\Http\Requests;

use App\Models\PortalWebhook;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->route('portalWebhook') instanceof PortalWebhook) {
            return true;
        }

        return $this->user()?->canReturnProcurementGoods() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('reason');

        $this->merge([
            'reason' => is_string($reason) ? trim($reason) : $reason,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'returned_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:10000'],
            'items' => ['required', 'array', 'list', 'min:1', 'max:50'],
            'items.*.goods_receipt_item_id' => ['required', 'integer', 'distinct:strict', 'exists:goods_receipt_items,id'],
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
}
