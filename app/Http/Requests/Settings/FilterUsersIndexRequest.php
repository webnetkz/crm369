<?php

namespace App\Http\Requests\Settings;

use App\Models\UserGroup;
use App\Support\PerPageOptions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterUsersIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->normalizeString($this->input('search')),
            'status' => $this->normalizeString($this->input('status')),
            'group' => $this->normalizeString($this->input('group')),
            'registered_from' => $this->normalizeString($this->input('registered_from')),
            'registered_to' => $this->normalizeString($this->input('registered_to')),
            'per_page' => (int) $this->input('per_page', PerPageOptions::DEFAULT),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'group' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null || $value === 'none') {
                        return;
                    }

                    if (! ctype_digit($value) || ! UserGroup::query()->whereKey((int) $value)->exists()) {
                        $fail(__('validation.exists', ['attribute' => $attribute]));
                    }
                },
            ],
            'registered_from' => ['nullable', 'date_format:Y-m-d'],
            'registered_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:registered_from'],
            'per_page' => ['required', 'integer', Rule::in(PerPageOptions::allowed())],
        ];
    }

    /**
     * @return array{
     *     search: string,
     *     status: string,
     *     group: string,
     *     registered_from: string,
     *     registered_to: string,
     *     per_page: int
     * }
     */
    public function filters(): array
    {
        return [
            'search' => $this->validated('search') ?? '',
            'status' => $this->validated('status') ?? '',
            'group' => $this->validated('group') ?? '',
            'registered_from' => $this->validated('registered_from') ?? '',
            'registered_to' => $this->validated('registered_to') ?? '',
            'per_page' => $this->validated('per_page') ?? PerPageOptions::DEFAULT,
        ];
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
