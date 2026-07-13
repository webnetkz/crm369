<?php

namespace App\Http\Requests;

use App\Models\PortalWebhook;
use App\Models\ReferenceDirectory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateReferenceDirectoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->route('portalWebhook') instanceof PortalWebhook) {
            return $this->route('referenceDirectory') instanceof ReferenceDirectory;
        }

        return ($this->route('referenceDirectory') instanceof ReferenceDirectory)
            && ($this->user()?->canManageDirectories() ?? false);
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
            'csv_exchange_enabled' => $this->has('csv_exchange_enabled')
                ? $this->boolean('csv_exchange_enabled')
                : true,
            'columns' => collect((array) $this->input('columns', []))
                ->filter(fn (mixed $column): bool => is_array($column))
                ->map(fn (array $column): array => [
                    'key' => is_string($column['key'] ?? null) ? trim($column['key']) : '',
                    'label' => is_string($column['label'] ?? null) ? trim($column['label']) : '',
                    'type' => is_string($column['type'] ?? null) ? trim($column['type']) : ReferenceDirectory::FIELD_TYPE_TEXT,
                    'is_required' => (bool) ($column['is_required'] ?? false),
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
        /** @var ReferenceDirectory|null $referenceDirectory */
        $referenceDirectory = $this->route('referenceDirectory');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('reference_directories', 'slug')->ignore($referenceDirectory?->id)],
            'description' => ['nullable', 'string', 'max:10000'],
            'csv_exchange_enabled' => ['required', 'boolean'],
            'columns' => ['required', 'array', 'list', 'min:1', 'max:50'],
            'columns.*.key' => ['nullable', 'string', 'max:50'],
            'columns.*.label' => ['required', 'string', 'max:255'],
            'columns.*.type' => ['required', 'string', Rule::in(ReferenceDirectory::availableColumnTypes())],
            'columns.*.is_required' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator): mixed => $this->validateColumns($validator)];
    }

    /**
     * @return array{name: string, slug: string, description: ?string, csv_exchange_enabled: bool, columns: array<int, array{key: string, label: string, type: string, is_required: bool}>}
     */
    public function directoryPayload(): array
    {
        return [
            'name' => $this->validated('name'),
            'slug' => $this->validated('slug'),
            'description' => $this->validated('description'),
            'csv_exchange_enabled' => (bool) $this->validated('csv_exchange_enabled'),
            'columns' => ReferenceDirectory::normalizeColumns($this->validated('columns', [])),
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function validateColumns(Validator $validator): void
    {
        if (count(ReferenceDirectory::normalizeColumns($this->validated('columns', []))) > 0) {
            return;
        }

        $validator->errors()->add('columns', __('ui.directories.validation_columns_required'));
    }
}
