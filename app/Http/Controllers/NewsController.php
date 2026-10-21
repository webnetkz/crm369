<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNewsRequest;
use App\Models\News;
use App\Support\NewsManager;
use App\Support\NewsPageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(StoreNewsRequest $request, NewsManager $newsManager): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $news = $newsManager->create($request, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.news.created_success')]);

        return to_route('news.show', $news);
    }

    public function update(StoreNewsRequest $request, News $news, NewsManager $newsManager): RedirectResponse
    {
        $newsManager->update($request, $news, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.news.updated_success')]);

        return to_route('news.show', $news);
    }

    public function destroy(Request $request, News $news, NewsManager $newsManager): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-news') ?? false, 403);

        $newsManager->delete($news);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.news.deleted_success')]);

        return to_route('news.index');
    }

    private function renderPage(Request $request, NewsPageData $pageData, ?News $selectedNews): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return Inertia::render('news/Index', $pageData->buildForViewer(
            $user,
            $selectedNews,
            $request->boolean('create'),
        ));
    }
}
