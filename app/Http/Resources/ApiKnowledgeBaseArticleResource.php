<?php

namespace App\Http\Resources;

use App\Models\KnowledgeBaseArticle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ApiKnowledgeBaseArticleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var KnowledgeBaseArticle $article */
        $article = $this->resource;

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
                ->map(function (mixed $block): mixed {
                    if (! is_array($block)) {
                        return $block;
                    }

                    $imagePath = data_get($block, 'image_path');

                    return [
                        ...$block,
                        'image_url' => is_string($imagePath) && $imagePath !== ''
                            ? Storage::disk('public')->url($imagePath)
                            : null,
                    ];
                })
                ->values()
                ->all(),
            'children' => $article->relationLoaded('children')
                ? ApiKnowledgeBaseArticleResource::collection($article->children)->resolve()
                : [],
            'created_at' => $article->created_at?->toISOString(),
            'updated_at' => $article->updated_at?->toISOString(),
        ];
    }
}
