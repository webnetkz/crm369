<?php

namespace App\Http\Requests;

use App\Models\News;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreNewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-news') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $title = $this->input('title');
        $slug = $this->input('slug');

        $this->merge([
            'title' => is_string($title) ? trim($title) : $title,
            'slug' => is_string($slug) && trim($slug) !== ''
                ? Str::slug($slug)
                : (is_string($title) ? Str::slug($title) : null),
            'excerpt' => $this->normalizeNullableString($this->input('excerpt')),
            'content' => $this->normalizeRequiredText($this->input('content')),
            'is_published' => $this->boolean('is_published', true),
            'remove_image' => $this->boolean('remove_image', false),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var News|null $news */
        $news = $this->route('news');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('news', 'slug')->ignore($news?->id)],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string', 'max:30000'],
            'is_published' => ['required', 'boolean'],
            'remove_image' => ['required', 'boolean'],
            'image_file' => ['nullable', 'image', 'max:5120'],
        ];
    }

    /**
     * @return array{title: string, slug: string, excerpt: string|null, content: string, is_published: bool}
     */
    public function payload(): array
    {
        return [
            'title' => (string) $this->validated('title'),
            'slug' => (string) $this->validated('slug'),
            'excerpt' => $this->validated('excerpt'),
            'content' => (string) $this->validated('content'),
            'is_published' => $this->boolean('is_published'),
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function normalizeRequiredText(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return str_replace(["\r\n", "\r"], "\n", trim($value));
    }
}
