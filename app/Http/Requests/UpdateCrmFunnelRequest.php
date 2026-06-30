<?php

namespace App\Http\Requests;

use App\Models\CrmFunnel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCrmFunnelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $funnel = $this->route('crmFunnel');

        return $funnel instanceof CrmFunnel
            && ($this->user()?->canManageFunnel($funnel) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $slug = $this->input('slug');

        $this->merge([
            'name' => is_string($name) ? trim($name) : $name,
            'slug' => is_string($slug) && trim($slug) !== ''
                ? Str::slug($slug)
                : (is_string($name) ? Str::slug($name) : null),
            'description' => $this->normalizeNullableString($this->input('description')),
            'color' => $this->normalizeNullableString($this->input('color')),
            'is_active' => $this->boolean('is_active', true),
            'group_ids' => array_values(array_filter(
                (array) $this->input('group_ids', []),
                fn (mixed $value): bool => is_numeric($value) && (int) $value > 0,
            )),
            'deal_fields' => collect((array) $this->input('deal_fields', []))
                ->filter(fn (mixed $field): bool => is_array($field))
                ->map(fn (array $field): array => [
                    'key' => is_string($field['key'] ?? null) ? trim($field['key']) : '',
                    'label' => is_string($field['label'] ?? null) ? trim($field['label']) : '',
                    'type' => is_string($field['type'] ?? null) ? trim($field['type']) : CrmFunnel::FIELD_TYPE_TEXT,
                    'is_required' => (bool) ($field['is_required'] ?? false),
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var CrmFunnel|null $funnel */
        $funnel = $this->route('crmFunnel');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('crm_funnels', 'slug')->ignore($funnel?->id)],
            'description' => ['nullable', 'string', 'max:10000'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_active' => ['required', 'boolean'],
            'group_ids' => ['nullable', 'array', 'list'],
            'group_ids.*' => ['integer', 'distinct:strict', 'exists:user_groups,id'],
            'deal_fields' => ['nullable', 'array', 'list'],
            'deal_fields.*.key' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_]+$/'],
            'deal_fields.*.label' => ['required', 'string', 'max:255'],
            'deal_fields.*.type' => ['required', 'string', Rule::in(CrmFunnel::availableFieldTypes())],
            'deal_fields.*.is_required' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function funnelPayload(): array
    {
        return [
            'name' => $this->validated('name'),
            'slug' => $this->validated('slug'),
            'description' => $this->validated('description'),
            'color' => $this->validated('color'),
            'is_active' => $this->boolean('is_active'),
            'deal_fields' => CrmFunnel::normalizeDealFields($this->validated('deal_fields', [])),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function groupIds(): array
    {
        return collect($this->validated('group_ids', []))
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
