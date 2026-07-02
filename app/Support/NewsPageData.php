<?php

namespace App\Support;

use App\Models\News;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class NewsPageData
{
    /**
     * @param  Collection<int, News>  $newsItems
     * @return array<string, mixed>
     */
    public function build(Collection $newsItems, ?News $activeNews, bool $canManage, bool $shouldOpenCreate): array
    {
        return [
            'newsItems' => $newsItems
                ->map(fn (News $news): array => $this->serializeListItem($news))
                ->values()
                ->all(),
            'activeNews' => $activeNews ? $this->serializeActiveItem($activeNews) : null,
            'can' => [
                'manage' => $canManage,
            ],
            'editor' => [
                'shouldOpenCreate' => $shouldOpenCreate,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeListItem(News $news): array
    {
        return [
            'id' => $news->id,
            'title' => $news->title,
            'slug' => $news->slug,
            'excerpt' => $news->excerpt,
            'image_url' => $this->imageUrl($news->image_path),
            'is_published' => $news->is_published,
            'published_at' => $news->published_at?->toISOString(),
            'updated_at' => $news->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeActiveItem(News $news): array
    {
        return [
            ...$this->serializeListItem($news),
            'content' => $news->content,
            'author' => $news->creator
                ? [
                    'name' => $news->creator->name,
                    'last_name' => $news->creator->last_name,
                ]
                : null,
        ];
    }

    private function imageUrl(?string $path): ?string
    {
        return is_string($path) && $path !== ''
            ? Storage::disk('public')->url($path)
            : null;
    }
}
