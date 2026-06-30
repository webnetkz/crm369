<?php

namespace App\Http\Requests;

use App\Support\PerPageOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterNotificationsIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->normalizeString($this->input('status')) ?? 'all',
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
            'status' => ['required', 'string', Rule::in(['all', 'unread', 'read'])],
            'per_page' => ['required', 'integer', Rule::in(PerPageOptions::allowed())],
        ];
    }

    /**
     * @return array{status: string, per_page: int}
     */
    public function filters(): array
    {
        return [
            'status' => $this->validated('status') ?? 'all',
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
