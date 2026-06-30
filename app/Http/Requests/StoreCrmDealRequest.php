<?php

namespace App\Http\Requests;

use App\Models\CrmDeal;
use App\Models\CrmFunnel;
use App\Models\CrmFunnelStage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCrmDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        $funnel = $this->resolvedFunnel();

        return $funnel !== null
            && ($this->user()?->canAccessFunnel($funnel) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'crm_funnel_stage_id' => is_numeric($this->input('crm_funnel_stage_id'))
                ? (int) $this->input('crm_funnel_stage_id')
                : null,
            'responsible_user_id' => is_numeric($this->input('responsible_user_id'))
                ? (int) $this->input('responsible_user_id')
                : null,
            'title' => is_string($this->input('title')) ? trim($this->input('title')) : $this->input('title'),
            'company_name' => $this->normalizeNullableString($this->input('company_name')),
            'contact_name' => $this->normalizeNullableString($this->input('contact_name')),
            'contact_phone' => $this->normalizeNullableString($this->input('contact_phone')),
            'contact_email' => $this->normalizeNullableString($this->input('contact_email')),
            'amount' => $this->normalizeNullableString($this->input('amount')),
            'currency' => $this->normalizeCurrency($this->input('currency')),
            'expected_close_at' => $this->normalizeNullableString($this->input('expected_close_at')),
            'description' => $this->normalizeNullableString($this->input('description')),
            'sort_order' => $this->filled('sort_order') ? max(0, (int) $this->input('sort_order')) : null,
            'custom_fields' => is_array($this->input('custom_fields')) ? $this->input('custom_fields') : [],
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'crm_funnel_stage_id' => ['required', 'integer', 'exists:crm_funnel_stages,id'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'expected_close_at' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:10000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateDealContext($validator)];
    }

    /**
     * @return array<string, mixed>
     */
    public function dealPayload(CrmFunnel $funnel): array
    {
        return [
            'crm_funnel_id' => $funnel->id,
            'crm_funnel_stage_id' => (int) $this->validated('crm_funnel_stage_id'),
            'responsible_user_id' => $this->validated('responsible_user_id'),
            'title' => $this->validated('title'),
            'company_name' => $this->validated('company_name'),
            'contact_name' => $this->validated('contact_name'),
            'contact_phone' => $this->validated('contact_phone'),
            'contact_email' => $this->validated('contact_email'),
            'amount' => $this->validated('amount'),
            'currency' => $this->validated('currency'),
            'expected_close_at' => $this->validated('expected_close_at'),
            'description' => $this->validated('description'),
            'custom_fields' => $this->normalizedCustomFields($funnel),
        ];
    }

    public function sortOrder(): ?int
    {
        $sortOrder = $this->validated('sort_order');

        return is_numeric($sortOrder) ? (int) $sortOrder : null;
    }

    protected function resolvedFunnel(): ?CrmFunnel
    {
        $routeFunnel = $this->route('crmFunnel');

        if ($routeFunnel instanceof CrmFunnel) {
            return $routeFunnel;
        }

        $routeDeal = $this->route('crmDeal');

        if ($routeDeal instanceof CrmDeal) {
            return $routeDeal->relationLoaded('funnel') ? $routeDeal->funnel : $routeDeal->funnel()->first();
        }

        return null;
    }

    private function validateDealContext(Validator $validator): void
    {
        $funnel = $this->resolvedFunnel();

        if (! $funnel) {
            return;
        }

        $stage = CrmFunnelStage::query()->find($this->validated('crm_funnel_stage_id'));

        if (! $stage || $stage->crm_funnel_id !== $funnel->id) {
            $validator->errors()->add('crm_funnel_stage_id', __('ui.funnels.validation_stage_funnel'));
        }

        foreach ($funnel->dealFieldDefinitions() as $field) {
            $value = data_get($this->validated('custom_fields', []), $field['key']);
            $hasValue = ! (is_null($value) || (is_string($value) && trim($value) === ''));

            if ($field['is_required'] && ! $hasValue) {
                $validator->errors()->add("custom_fields.{$field['key']}", __('ui.funnels.validation_field_required', ['field' => $field['label']]));

                continue;
            }

            if (! $hasValue) {
                continue;
            }

            if ($field['type'] === CrmFunnel::FIELD_TYPE_NUMBER && ! is_numeric($value)) {
                $validator->errors()->add("custom_fields.{$field['key']}", __('ui.funnels.validation_field_number', ['field' => $field['label']]));
            }

            if ($field['type'] === CrmFunnel::FIELD_TYPE_DATE && ! $this->isDateString($value)) {
                $validator->errors()->add("custom_fields.{$field['key']}", __('ui.funnels.validation_field_date', ['field' => $field['label']]));
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedCustomFields(CrmFunnel $funnel): array
    {
        $values = (array) $this->validated('custom_fields', []);
        $normalized = [];

        foreach ($funnel->dealFieldDefinitions() as $field) {
            $value = data_get($values, $field['key']);

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '') {
                $value = null;
            }

            $normalized[$field['key']] = $value;
        }

        return $normalized;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function normalizeCurrency(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_strtoupper(trim($value));
    }

    private function isDateString(mixed $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        return strtotime($value) !== false;
    }
}
