<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterChatsIndexRequest extends FormRequest
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
            'mode' => $this->normalizeString($this->input('mode')) ?? 'chats',
            'conversation' => $this->normalizeInteger($this->input('conversation')),
            'contact' => $this->normalizeInteger($this->input('contact')),
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
            'mode' => ['required', 'string', Rule::in(['chats', 'search'])],
            'conversation' => ['nullable', 'integer'],
            'contact' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array{mode: string, conversation: int|null, contact: int|null}
     */
    public function filters(): array
    {
        return [
            'mode' => $this->validated('mode') ?? 'chats',
            'conversation' => $this->validated('conversation'),
            'contact' => $this->validated('contact'),
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

    private function normalizeInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }
}
