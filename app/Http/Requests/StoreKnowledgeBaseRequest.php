<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreKnowledgeBaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-knowledge-bases') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');
        $title = $this->input('title');

        $this->merge([
            'title' => is_string($title) ? trim($title) : $title,
            'slug' => is_string($slug) && trim($slug) !== ''
                ? Str::slug($slug)
                : (is_string($title) ? Str::slug($title) : null),
            'description' => $this->normalizeNullableString($this->input('description')),
            'is_published' => $this->boolean('is_published', true),
            'user_group_ids' => array_values(array_filter(
                (array) $this->input('user_group_ids', []),
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
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('knowledge_bases', 'slug')],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_published' => ['required', 'boolean'],
            'user_group_ids' => ['nullable', 'array', 'list'],
            'user_group_ids.*' => ['integer', 'exists:user_groups,id'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function userGroupIds(): array
    {
        return collect($this->validated('user_group_ids', []))
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->values()
            ->all();
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
