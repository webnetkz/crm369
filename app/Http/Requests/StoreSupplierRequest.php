<?php

namespace App\Http\Requests;

use App\Models\PortalWebhook;
use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
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
        $this->merge([
            'name' => $this->normalizeRequiredString($this->input('name')),
            'bin' => $this->normalizeNullableString($this->input('bin')),
            'contact_person' => $this->normalizeNullableString($this->input('contact_person')),
            'email' => $this->normalizeNullableString($this->input('email')),
            'phone' => $this->normalizeNullableString($this->input('phone')),
            'currency' => strtoupper((string) $this->input('currency', 'KZT')),
            'notes' => $this->normalizeNullableString($this->input('notes')),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $supplier = $this->route('supplier');

        return [
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'name' => ['required', 'string', 'max:255'],
            'bin' => [
                'nullable',
                'digits:12',
                Rule::unique(Supplier::class, 'bin')->ignore($supplier instanceof Supplier ? $supplier->id : null),
            ],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', Rule::in(['KZT', 'USD', 'EUR', 'RUB'])],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'lead_time_days' => ['required', 'integer', 'min:0', 'max:365'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->validated();
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
