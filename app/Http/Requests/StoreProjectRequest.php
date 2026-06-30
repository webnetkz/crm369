<?php

namespace App\Http\Requests;

use App\Support\ApiRequestContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
            'is_archived' => $this->boolean('is_archived', false),
            'member_user_ids' => array_values(array_filter(
                (array) $this->input('member_user_ids', []),
                fn (mixed $value): bool => is_numeric($value) && (int) $value > 0,
            )),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('projects', 'slug')],
            'description' => ['nullable', 'string', 'max:10000'],
            'is_archived' => ['required', 'boolean'],
            'member_user_ids' => ['nullable', 'array', 'list'],
            'member_user_ids.*' => ['integer', 'distinct:strict', 'exists:users,id'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function memberUserIds(): array
    {
        $ownerId = ApiRequestContext::subject($this)->id;

        return collect($this->validated('member_user_ids', []))
            ->map(fn (mixed $value): int => (int) $value)
            ->when($ownerId !== null, fn ($collection) => $collection->push($ownerId))
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
