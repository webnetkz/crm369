<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNewsRequest;
use App\Models\News;
use App\Support\NewsPageData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class NewsController extends Controller
{
    public function index(Request $request, NewsPageData $pageData): Response
    {
        return $this->renderPage($request, $pageData, null);
    }

    public function show(Request $request, News $news, NewsPageData $pageData): Response
    {
        return $this->renderPage($request, $pageData, $news);
    }

    public function store(StoreNewsRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $news = News::query()->create([
            ...$request->payload(),
            'image_path' => $request->file('image_file')?->store('news', 'public'),
            'published_at' => $request->boolean('is_published') ? now() : null,
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.news.created_success')]);

        return to_route('news.show', $news);
    }

    public function update(StoreNewsRequest $request, News $news): RedirectResponse
    {
        $currentImagePath = $news->image_path;
        $nextImagePath = $currentImagePath;

        if ($request->boolean('remove_image')) {
            $nextImagePath = null;
        }

        if ($request->file('image_file') !== null) {
            $nextImagePath = $request->file('image_file')?->store('news', 'public');
        }

        $news->update([
            ...$request->payload(),
            'image_path' => $nextImagePath,
            'published_at' => $request->boolean('is_published')
                ? ($news->published_at ?? now())
                : null,
            'updated_by_user_id' => $request->user()?->id,
        ]);

        if (
            is_string($currentImagePath)
            && $currentImagePath !== ''
            && $currentImagePath !== $nextImagePath
        ) {
            Storage::disk('public')->delete($currentImagePath);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.news.updated_success')]);

        return to_route('news.show', $news);
    }

    public function destroy(Request $request, News $news): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-news') ?? false, 403);

        $imagePath = $news->image_path;
        $news->delete();

        if (is_string($imagePath) && $imagePath !== '') {
            Storage::disk('public')->delete($imagePath);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.news.deleted_success')]);

        return to_route('news.index');
    }

    private function renderPage(Request $request, NewsPageData $pageData, ?News $selectedNews): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $canManage = $user->can('manage-news');
        $newsItems = News::query()
            ->with('creator:id,name,last_name')
            ->when(! $canManage, fn (Builder $query) => $query->where('is_published', true))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        if ($selectedNews !== null) {
            $selectedNews = $newsItems->firstWhere('id', $selectedNews->id);
            abort_unless($selectedNews !== null, 404);
        }

        return Inertia::render('news/Index', $pageData->build(
            $newsItems,
            $selectedNews,
            $canManage,
            $request->boolean('create') && $canManage,
        ));
    }
}
