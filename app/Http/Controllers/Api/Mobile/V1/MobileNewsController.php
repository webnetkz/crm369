<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNewsRequest;
use App\Models\News;
use App\Support\NewsManager;
use App\Support\NewsPageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileNewsController extends Controller
{
    /**
     * The news feed visible to the user (the web news page props).
     */
    public function index(Request $request, NewsPageData $pageData): JsonResponse
    {
        return response()->json([
            'data' => $this->pageData($pageData->buildForViewer($request->user(), null, $request->boolean('create'))),
        ]);
    }

    /**
     * The news feed visible to the user with the requested news item opened.
     */
    public function show(Request $request, News $news, NewsPageData $pageData): JsonResponse
    {
        return response()->json([
            'data' => $this->pageData($pageData->buildForViewer($request->user(), $news, $request->boolean('create'))),
        ]);
    }

    public function store(StoreNewsRequest $request, NewsManager $newsManager, NewsPageData $pageData): JsonResponse
    {
        $news = $newsManager->create($request, $request->user());

        return response()->json([
            'message' => __('ui.news.created_success'),
            'data' => $this->newsItem($news, $pageData),
        ], 201);
    }

    public function update(StoreNewsRequest $request, News $news, NewsManager $newsManager, NewsPageData $pageData): JsonResponse
    {
        $newsManager->update($request, $news, $request->user());

        return response()->json([
            'message' => __('ui.news.updated_success'),
            'data' => $this->newsItem($news, $pageData),
        ]);
    }

    public function destroy(Request $request, News $news, NewsManager $newsManager): JsonResponse
    {
        abort_unless($request->user()?->can('manage-news') ?? false, 403);

        $newsManager->delete($news);

        return response()->json([
            'message' => __('ui.news.deleted_success'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $pageData
     * @return array<string, mixed>
     */
    private function pageData(array $pageData): array
    {
        $newsItems = $pageData['newsItems'] ?? null;

        if (is_array($newsItems)) {
            $pageData['newsItems'] = array_map(
                fn (mixed $newsItem): mixed => is_array($newsItem) ? $this->withAbsoluteImageUrl($newsItem) : $newsItem,
                $newsItems,
            );
        }

        if (is_array($pageData['activeNews'] ?? null)) {
            $pageData['activeNews'] = $this->withAbsoluteImageUrl($pageData['activeNews']);
        }

        return $pageData;
    }

    /**
     * @return array<string, mixed>
     */
    private function newsItem(News $news, NewsPageData $pageData): array
    {
        return $this->withAbsoluteImageUrl(
            $pageData->serializeActiveItem($news->refresh()->load('creator:id,name,last_name')),
        );
    }

    /**
     * Native clients cannot resolve paths relative to the portal, so image URLs are always absolute.
     *
     * @param  array<mixed>  $newsItem
     * @return array<mixed>
     */
    private function withAbsoluteImageUrl(array $newsItem): array
    {
        $imageUrl = $newsItem['image_url'] ?? null;

        if (is_string($imageUrl) && $imageUrl !== '') {
            $newsItem['image_url'] = url($imageUrl);
        }

        return $newsItem;
    }
}
