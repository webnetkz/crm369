<?php

namespace App\Support;

use App\Models\KnowledgeBase;
use App\Models\KnowledgeBaseArticle;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class KnowledgeBasePageData
{
    /**
     * @param  Collection<int, KnowledgeBase>  $bases
     * @return array<string, mixed>
     */
    public function build(User $user, Collection $bases, ?KnowledgeBase $selectedBase, ?KnowledgeBaseArticle $selectedArticle, bool $canManage): array
    {
        $activeBase = $selectedBase;

        if (! $activeBase && $bases->isNotEmpty()) {
            $activeBase = $bases->first();
        }

        if ($activeBase && ! $activeBase->relationLoaded('articles')) {
            $activeBase->load([
                'groups:id,name,description',
                'articles' => fn ($query) => $query
                    ->when(! $canManage, fn ($articleQuery) => $articleQuery->where('is_published', true))
                    ->orderBy('sort_order')
                    ->orderBy('title'),
            ]);
        }

        $activeArticle = $selectedArticle;

        if ($activeBase && ! $activeArticle) {
            $activeArticle = $activeBase->articles->first();
        }

        return [
            'bases' => $bases
                ->map(fn (KnowledgeBase $knowledgeBase): array => $this->serializeBaseListItem($knowledgeBase))
                ->values()
                ->all(),
            'activeBase' => $activeBase ? $this->serializeActiveBase($activeBase) : null,
            'activeArticle' => $activeArticle ? $this->serializeArticle($activeArticle) : null,
            'can' => [
                'manage' => $canManage,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeBaseListItem(KnowledgeBase $knowledgeBase): array
    {
        return [
            'id' => $knowledgeBase->id,
            'title' => $knowledgeBase->title,
            'slug' => $knowledgeBase->slug,
            'description' => $knowledgeBase->description,
            'is_published' => $knowledgeBase->is_published,
            'article_count' => $knowledgeBase->articles_count ?? $knowledgeBase->articles()->count(),
            'updated_at' => $knowledgeBase->updated_at?->toISOString(),
            'groups' => $knowledgeBase->relationLoaded('groups')
                ? $knowledgeBase->groups->map(fn ($group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'display_name' => $group->displayName(),
                ])->values()->all()
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeActiveBase(KnowledgeBase $knowledgeBase): array
    {
        return [
            'id' => $knowledgeBase->id,
            'title' => $knowledgeBase->title,
            'slug' => $knowledgeBase->slug,
            'description' => $knowledgeBase->description,
            'is_published' => $knowledgeBase->is_published,
            'groups' => $knowledgeBase->groups
                ->map(fn ($group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'display_name' => $group->displayName(),
                ])
                ->values()
                ->all(),
            'articles' => $this->articleTree($knowledgeBase->articles),
        ];
    }

    /**
     * @param  Collection<int, KnowledgeBaseArticle>  $articles
     * @return array<int, array<string, mixed>>
     */
    private function articleTree(Collection $articles, ?int $parentId = null): array
    {
        return $articles
            ->where('parent_id', $parentId)
            ->sortBy(['sort_order', 'title'])
            ->values()
            ->map(fn (KnowledgeBaseArticle $article): array => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $article->excerpt,
                'is_published' => $article->is_published,
                'sort_order' => $article->sort_order,
                'children' => $this->articleTree($articles, $article->id),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeArticle(KnowledgeBaseArticle $article): array
    {
        return [
            'id' => $article->id,
            'knowledge_base_id' => $article->knowledge_base_id,
            'parent_id' => $article->parent_id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'sort_order' => $article->sort_order,
            'is_published' => $article->is_published,
            'blocks' => collect($article->blocks ?? [])
                ->map(function (mixed $block): array {
                    $imagePath = is_array($block) ? data_get($block, 'image_path') : null;

                    return [
                        'type' => is_array($block) ? data_get($block, 'type') : null,
                        'content' => is_array($block) ? data_get($block, 'content') : null,
                        'heading_level' => is_array($block) ? data_get($block, 'heading_level') : null,
                        'items' => is_array($block) ? data_get($block, 'items', []) : [],
                        'ordered' => is_array($block) ? (bool) data_get($block, 'ordered', false) : false,
                        'image_path' => $imagePath,
                        'image_url' => is_string($imagePath) && $imagePath !== '' ? Storage::disk('public')->url($imagePath) : null,
                        'caption' => is_array($block) ? data_get($block, 'caption') : null,
                    ];
                })
                ->values()
                ->all(),
            'updated_at' => $article->updated_at?->toISOString(),
        ];
    }
}
