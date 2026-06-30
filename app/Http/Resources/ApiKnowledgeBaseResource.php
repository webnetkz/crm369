<?php

namespace App\Http\Resources;

use App\Models\KnowledgeBase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiKnowledgeBaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var KnowledgeBase $knowledgeBase */
        $knowledgeBase = $this->resource;

        return [
            'id' => $knowledgeBase->id,
            'title' => $knowledgeBase->title,
            'slug' => $knowledgeBase->slug,
            'description' => $knowledgeBase->description,
            'is_published' => $knowledgeBase->is_published,
            'article_count' => $knowledgeBase->articles_count,
            'groups' => $knowledgeBase->relationLoaded('groups')
                ? $knowledgeBase->groups->map(fn ($group): array => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'display_name' => $group->displayName(),
                ])->values()->all()
                : [],
            'articles' => $knowledgeBase->relationLoaded('articles')
                ? ApiKnowledgeBaseArticleResource::collection($knowledgeBase->articles)->resolve()
                : [],
            'created_at' => $knowledgeBase->created_at?->toISOString(),
            'updated_at' => $knowledgeBase->updated_at?->toISOString(),
        ];
    }
}
