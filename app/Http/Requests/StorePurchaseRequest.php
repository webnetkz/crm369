<?php

namespace App\Http\Requests;

use App\Models\PortalWebhook;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->route('portalWebhook') instanceof PortalWebhook) {
            return true;
        }

        return $this->user()?->canAccessProcurement() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $items = collect(is_array($this->input('items')) ? $this->input('items') : [])
            ->map(function (mixed $item): mixed {
                if (! is_array($item)) {
                    return $item;
                }

                return [
                    ...$item,
                    'item_name' => $this->normalizeRequiredString($item['item_name'] ?? null),
                    'sku' => $this->normalizeNullableString($item['sku'] ?? null),
                    'unit' => $this->normalizeRequiredString($item['unit'] ?? 'pcs'),
                    'production_reference' => $this->normalizeNullableString($item['production_reference'] ?? null),
                    'notes' => $this->normalizeNullableString($item['notes'] ?? null),
                ];
            })
            ->all();

        $this->merge([
            'title' => $this->normalizeRequiredString($this->input('title')),
            'currency' => strtoupper((string) $this->input('currency', 'KZT')),
            'justification' => $this->normalizeNullableString($this->input('justification')),
            'items' => $items,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'needed_at' => ['nullable', 'date'],
            'budget_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['required', 'string', Rule::in(['KZT', 'USD', 'EUR', 'RUB'])],
            'justification' => ['nullable', 'string', 'max:10000'],
            'items' => ['required', 'array', 'list', 'min:1', 'max:50'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.sku' => ['nullable', 'string', 'max:255'],
            'items.*.unit' => ['required', 'string', 'max:32'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'items.*.target_unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'items.*.warehouse_place_id' => ['nullable', 'integer', 'exists:warehouse_places,id'],
            'items.*.warehouse_item_id' => ['nullable', 'integer', 'exists:warehouse_items,id'],
            'items.*.production_reference' => ['nullable', 'string', 'max:255'],
            'items.*.notes' => ['nullable', 'string', 'max:10000'],
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

                $estimatedTotal = collect($this->validated('items'))
                    ->sum(fn (array $item): float => (int) $item['quantity'] * (float) $item['target_unit_price']);

                if ((float) $this->validated('budget_amount') < $estimatedTotal) {
                    $validator->errors()->add('budget_amount', __('ui.procurement.validation.budget_below_estimate'));
                }
            },
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->safe()->except('items');
    }

    /** @return array<int, array<string, mixed>> */
    public function itemPayloads(): array
    {
        return $this->validated('items');
    }

    private function normalizeRequiredString(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
