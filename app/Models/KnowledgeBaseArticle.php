<?php

namespace App\Models;

use Database\Factories\KnowledgeBaseArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $knowledge_base_id
 * @property int|null $parent_id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property array<int, array<string, mixed>> $blocks
 * @property int $sort_order
 * @property bool $is_published
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['knowledge_base_id', 'parent_id', 'title', 'slug', 'excerpt', 'blocks', 'sort_order', 'is_published', 'created_by_user_id', 'updated_by_user_id'])]
class KnowledgeBaseArticle extends Model
{
    public const string BLOCK_PARAGRAPH = 'paragraph';
    public const string BLOCK_HEADING = 'heading';
    public const string BLOCK_LIST = 'list';
    public const string BLOCK_IMAGE = 'image';

    /** @use HasFactory<KnowledgeBaseArticleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $article): void {
            if (! is_string($article->slug) || trim($article->slug) === '') {
                $article->slug = Str::slug($article->title);
            }
        });

        static::deleting(function (self $article): void {
            collect($article->blocks ?? [])
                ->map(fn (mixed $block): ?string => is_array($block) ? data_get($block, 'image_path') : null)
                ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
                ->each(fn (string $path): bool => Storage::disk('public')->delete($path));
        });
    }

    /**
     * @return BelongsTo<KnowledgeBase, $this>
     */
    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    /**
     * @return BelongsTo<KnowledgeBaseArticle, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<KnowledgeBaseArticle, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('title');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * @return array<int, string>
     */
    public static function availableBlocks(): array
    {
        return [
            self::BLOCK_PARAGRAPH,
            self::BLOCK_HEADING,
            self::BLOCK_LIST,
            self::BLOCK_IMAGE,
        ];
    }
}
