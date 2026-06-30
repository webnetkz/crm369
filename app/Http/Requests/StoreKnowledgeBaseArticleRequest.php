<?php

namespace App\Http\Requests;

use App\Models\KnowledgeBase;
use App\Models\KnowledgeBaseArticle;
use App\Support\KnowledgeBaseRichText;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreKnowledgeBaseArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-knowledge-bases') ?? false;
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
            'sort_order' => max(0, (int) $this->input('sort_order', 0)),
            'is_published' => $this->boolean('is_published', true),
            'blocks' => $this->normalizeInputBlocks(),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $knowledgeBase = $this->route('knowledgeBase');
        $knowledgeBaseId = $knowledgeBase instanceof KnowledgeBase ? $knowledgeBase->id : null;

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('knowledge_base_articles', 'id')->where(
                    fn ($query) => $knowledgeBaseId ? $query->where('knowledge_base_id', $knowledgeBaseId) : $query,
                ),
            ],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('knowledge_base_articles', 'slug')->where(
                    fn ($query) => $knowledgeBaseId ? $query->where('knowledge_base_id', $knowledgeBaseId) : $query,
                ),
            ],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_published' => ['required', 'boolean'],
            'blocks' => ['required', 'array', 'list'],
            'blocks.*' => ['array:type,content,heading_level,items,ordered,image_path,image_file,caption'],
            'blocks.*.type' => ['required', 'string', Rule::in(KnowledgeBaseArticle::availableBlocks())],
            'blocks.*.content' => ['nullable', 'string'],
            'blocks.*.heading_level' => ['nullable', 'integer', Rule::in([1, 2, 3])],
            'blocks.*.items' => ['nullable', 'array', 'list'],
            'blocks.*.items.*' => ['nullable', 'string', 'max:1000'],
            'blocks.*.ordered' => ['nullable', 'boolean'],
            'blocks.*.image_path' => ['nullable', 'string', 'max:2048'],
            'blocks.*.image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'blocks.*.caption' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateBlocks($validator)];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function normalizedBlockPayload(): array
    {
        return collect($this->validated('blocks'))
            ->values()
            ->map(function (mixed $block, int $index): array {
                $richText = app(KnowledgeBaseRichText::class);

                /** @var array<string, mixed> $block */
                return [
                    'type' => (string) ($block['type'] ?? ''),
                    'content' => $richText->sanitize($this->normalizeNullableString($block['content'] ?? null)),
                    'heading_level' => isset($block['heading_level']) ? (int) $block['heading_level'] : null,
                    'items' => collect($block['items'] ?? [])
                        ->map(fn (mixed $item): ?string => $richText->sanitize(is_string($item) ? trim($item) : null))
                        ->filter(fn (?string $item): bool => $richText->plainText($item) !== '')
                        ->values()
                        ->all(),
                    'ordered' => filter_var($block['ordered'] ?? false, FILTER_VALIDATE_BOOL),
                    'image_path' => $this->normalizeNullableString($block['image_path'] ?? null),
                    'image_file' => $this->file("blocks.$index.image_file"),
                    'caption' => $this->normalizeNullableString($block['caption'] ?? null),
                ];
            })
            ->all();
    }

    private function validateBlocks(Validator $validator): void
    {
        foreach ($this->normalizedBlockPayload() as $index => $block) {
            match ($block['type']) {
                KnowledgeBaseArticle::BLOCK_PARAGRAPH => $this->assertParagraph($validator, $index, $block),
                KnowledgeBaseArticle::BLOCK_HEADING => $this->assertHeading($validator, $index, $block),
                KnowledgeBaseArticle::BLOCK_LIST => $this->assertList($validator, $index, $block),
                KnowledgeBaseArticle::BLOCK_IMAGE => $this->assertImage($validator, $index, $block),
                default => null,
            };
        }
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function assertParagraph(Validator $validator, int $index, array $block): void
    {
        if (app(KnowledgeBaseRichText::class)->plainText($block['content'] ?? null) === '') {
            $validator->errors()->add("blocks.$index.content", __('ui.knowledge.validation_paragraph_content'));
        }
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function assertHeading(Validator $validator, int $index, array $block): void
    {
        if (app(KnowledgeBaseRichText::class)->plainText($block['content'] ?? null) === '') {
            $validator->errors()->add("blocks.$index.content", __('ui.knowledge.validation_heading_content'));
        }
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function assertList(Validator $validator, int $index, array $block): void
    {
        if (! is_array($block['items']) || $block['items'] === []) {
            $validator->errors()->add("blocks.$index.items", __('ui.knowledge.validation_list_items'));
        }
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function assertImage(Validator $validator, int $index, array $block): void
    {
        if (! $block['image_file'] && (! is_string($block['image_path']) || trim($block['image_path']) === '')) {
            $validator->errors()->add("blocks.$index.image_file", __('ui.knowledge.validation_image_required'));
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeInputBlocks(): array
    {
        return collect((array) $this->input('blocks', []))
            ->values()
            ->map(function (mixed $block): array {
                if (! is_array($block)) {
                    return [];
                }

                return [
                    'type' => $block['type'] ?? null,
                    'content' => $block['content'] ?? null,
                    'heading_level' => $block['heading_level'] ?? null,
                    'items' => array_values((array) ($block['items'] ?? [])),
                    'ordered' => filter_var($block['ordered'] ?? false, FILTER_VALIDATE_BOOL),
                    'image_path' => $block['image_path'] ?? null,
                    'caption' => $block['caption'] ?? null,
                ];
            })
            ->all();
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
