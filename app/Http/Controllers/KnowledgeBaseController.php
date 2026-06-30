<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKnowledgeBaseArticleRequest;
use App\Http\Requests\StoreKnowledgeBaseRequest;
use App\Http\Requests\UpdateKnowledgeBaseArticleRequest;
use App\Http\Requests\UpdateKnowledgeBaseRequest;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeBaseArticle;
use App\Models\UserGroup;
use App\Support\KnowledgeBasePageData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request, KnowledgeBasePageData $pageData): Response
    {
        return $this->renderPage($request, $pageData, null, null);
    }

    public function show(Request $request, KnowledgeBase $knowledgeBase, KnowledgeBasePageData $pageData): Response
    {
        return $this->renderPage($request, $pageData, $knowledgeBase, null);
    }

    public function article(Request $request, KnowledgeBase $knowledgeBase, KnowledgeBaseArticle $knowledgeBaseArticle, KnowledgeBasePageData $pageData): Response
    {
        abort_unless($knowledgeBaseArticle->knowledge_base_id === $knowledgeBase->id, 404);

        return $this->renderPage($request, $pageData, $knowledgeBase, $knowledgeBaseArticle);
    }

    public function store(StoreKnowledgeBaseRequest $request): RedirectResponse
    {
        $user = $request->user();

        $knowledgeBase = KnowledgeBase::query()->create([
            'title' => $request->validated('title'),
            'slug' => $request->validated('slug'),
            'description' => $request->validated('description'),
            'is_published' => $request->validated('is_published'),
            'created_by_user_id' => $user?->id,
            'updated_by_user_id' => $user?->id,
        ]);

        $knowledgeBase->groups()->sync($request->userGroupIds());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.knowledge.base_created_success')]);

        return to_route('knowledge-bases.show', $knowledgeBase);
    }

    public function update(UpdateKnowledgeBaseRequest $request, KnowledgeBase $knowledgeBase): RedirectResponse
    {
        $knowledgeBase->update([
            'title' => $request->validated('title'),
            'slug' => $request->validated('slug'),
            'description' => $request->validated('description'),
            'is_published' => $request->validated('is_published'),
            'updated_by_user_id' => $request->user()?->id,
        ]);

        $knowledgeBase->groups()->sync($request->userGroupIds());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.knowledge.base_updated_success')]);

        return to_route('knowledge-bases.show', $knowledgeBase);
    }

    public function destroy(Request $request, KnowledgeBase $knowledgeBase): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-knowledge-bases') ?? false, 403);

        $knowledgeBase->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.knowledge.base_deleted_success')]);

        return to_route('knowledge-bases.index');
    }

    public function storeArticle(StoreKnowledgeBaseArticleRequest $request, KnowledgeBase $knowledgeBase): RedirectResponse
    {
        $article = DB::transaction(function () use ($knowledgeBase, $request): KnowledgeBaseArticle {
            $article = KnowledgeBaseArticle::query()->create([
                'knowledge_base_id' => $knowledgeBase->id,
                'parent_id' => $request->validated('parent_id'),
                'title' => $request->validated('title'),
                'slug' => $request->validated('slug'),
                'excerpt' => $request->validated('excerpt'),
                'blocks' => [],
                'sort_order' => $request->validated('sort_order'),
                'is_published' => $request->validated('is_published'),
                'created_by_user_id' => $request->user()?->id,
                'updated_by_user_id' => $request->user()?->id,
            ]);

            $article->update([
                'blocks' => $this->persistBlocks($knowledgeBase, $article, $request->normalizedBlockPayload()),
            ]);

            return $article;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.knowledge.article_created_success')]);

        return to_route('knowledge-bases.articles.show', [$knowledgeBase, $article]);
    }

    public function updateArticle(UpdateKnowledgeBaseArticleRequest $request, KnowledgeBase $knowledgeBase, KnowledgeBaseArticle $knowledgeBaseArticle): RedirectResponse
    {
        abort_unless($knowledgeBaseArticle->knowledge_base_id === $knowledgeBase->id, 404);

        DB::transaction(function () use ($knowledgeBase, $knowledgeBaseArticle, $request): void {
            $existingBlocks = is_array($knowledgeBaseArticle->blocks) ? $knowledgeBaseArticle->blocks : [];

            $knowledgeBaseArticle->update([
                'parent_id' => $request->validated('parent_id'),
                'title' => $request->validated('title'),
                'slug' => $request->validated('slug'),
                'excerpt' => $request->validated('excerpt'),
                'sort_order' => $request->validated('sort_order'),
                'is_published' => $request->validated('is_published'),
                'updated_by_user_id' => $request->user()?->id,
                'blocks' => $this->persistBlocks($knowledgeBase, $knowledgeBaseArticle, $request->normalizedBlockPayload(), $existingBlocks),
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.knowledge.article_updated_success')]);

        return to_route('knowledge-bases.articles.show', [$knowledgeBase, $knowledgeBaseArticle]);
    }

    public function destroyArticle(Request $request, KnowledgeBase $knowledgeBase, KnowledgeBaseArticle $knowledgeBaseArticle): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-knowledge-bases') ?? false, 403);
        abort_unless($knowledgeBaseArticle->knowledge_base_id === $knowledgeBase->id, 404);

        $knowledgeBaseArticle->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.knowledge.article_deleted_success')]);

        return to_route('knowledge-bases.show', $knowledgeBase);
    }

    private function renderPage(Request $request, KnowledgeBasePageData $pageData, ?KnowledgeBase $selectedBase, ?KnowledgeBaseArticle $selectedArticle): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $canManage = $user->can('manage-knowledge-bases');
        $bases = KnowledgeBase::query()
            ->with('groups:id,name,description')
            ->withCount([
                'articles' => fn ($query) => $query->when(! $canManage, fn ($articleQuery) => $articleQuery->where('is_published', true)),
            ])
            ->when(! $canManage, fn (Builder $query) => $query
                ->where('is_published', true)
                ->where(function (Builder $visibilityQuery) use ($user): void {
                    $visibilityQuery->whereDoesntHave('groups');

                    if (is_numeric($user->user_group_id)) {
                        $visibilityQuery->orWhereHas('groups', fn (Builder $groupQuery) => $groupQuery->where('user_groups.id', (int) $user->user_group_id));
                    }
                }))
            ->orderBy('title')
            ->get();

        if ($selectedBase) {
            $selectedBase->load([
                'groups:id,name,description',
                'articles' => fn ($query) => $query
                    ->when(! $canManage, fn ($articleQuery) => $articleQuery->where('is_published', true))
                    ->orderBy('sort_order')
                    ->orderBy('title'),
            ]);

            abort_unless($canManage || $selectedBase->isVisibleTo($user), 404);
        }

        if ($selectedArticle) {
            abort_unless($selectedBase !== null && $selectedArticle->knowledge_base_id === $selectedBase->id, 404);

            if (! $canManage && ! $selectedArticle->is_published) {
                abort(404);
            }
        }

        return Inertia::render('knowledge/Index', [
            ...$pageData->build($user, $bases, $selectedBase, $selectedArticle, $canManage),
            'groups' => $canManage
                ? UserGroup::query()
                    ->select(['id', 'name', 'description'])
                    ->orderBy('name')
                    ->get()
                    ->map(fn (UserGroup $group): array => [
                        'id' => $group->id,
                        'name' => $group->name,
                        'display_name' => $group->displayName(),
                    ])
                    ->values()
                    ->all()
                : [],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<int, array<string, mixed>>  $existingBlocks
     * @return array<int, array<string, mixed>>
     */
    private function persistBlocks(KnowledgeBase $knowledgeBase, KnowledgeBaseArticle $article, array $blocks, array $existingBlocks = []): array
    {
        $existingImagePaths = collect($existingBlocks)
            ->map(fn (array $block): ?string => data_get($block, 'image_path'))
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->values()
            ->all();

        $persistedBlocks = collect($blocks)
            ->map(function (array $block) use ($knowledgeBase, $article, $existingImagePaths): array {
                return match ($block['type']) {
                    KnowledgeBaseArticle::BLOCK_HEADING => [
                        'type' => KnowledgeBaseArticle::BLOCK_HEADING,
                        'content' => $block['content'],
                        'heading_level' => $block['heading_level'] ?? 2,
                    ],
                    KnowledgeBaseArticle::BLOCK_LIST => [
                        'type' => KnowledgeBaseArticle::BLOCK_LIST,
                        'items' => $block['items'],
                        'ordered' => (bool) $block['ordered'],
                    ],
                    KnowledgeBaseArticle::BLOCK_IMAGE => [
                        'type' => KnowledgeBaseArticle::BLOCK_IMAGE,
                        'image_path' => $this->storeImageBlock($knowledgeBase, $article, $block, $existingImagePaths),
                        'caption' => $block['caption'],
                    ],
                    default => [
                        'type' => KnowledgeBaseArticle::BLOCK_PARAGRAPH,
                        'content' => $block['content'],
                    ],
                };
            })
            ->values();

        $persistedImagePaths = $persistedBlocks
            ->map(fn (array $block): ?string => data_get($block, 'image_path'))
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->values()
            ->all();

        collect(array_diff($existingImagePaths, $persistedImagePaths))
            ->each(fn (string $path): bool => Storage::disk('public')->delete($path));

        return $persistedBlocks->all();
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<int, string>  $existingImagePaths
     */
    private function storeImageBlock(KnowledgeBase $knowledgeBase, KnowledgeBaseArticle $article, array $block, array $existingImagePaths): ?string
    {
        if ($block['image_file']) {
            return $block['image_file']->store(
                'knowledge-bases/'.$knowledgeBase->id.'/articles/'.$article->id,
                'public',
            );
        }

        $imagePath = is_string($block['image_path']) ? $block['image_path'] : null;

        if ($imagePath && in_array($imagePath, $existingImagePaths, true)) {
            return $imagePath;
        }

        return null;
    }
}
