<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKnowledgeBaseArticleRequest;
use App\Http\Requests\StoreKnowledgeBaseRequest;
use App\Http\Requests\UpdateKnowledgeBaseArticleRequest;
use App\Http\Requests\UpdateKnowledgeBaseRequest;
use App\Http\Resources\ApiKnowledgeBaseArticleResource;
use App\Http\Resources\ApiKnowledgeBaseResource;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeBaseArticle;
use App\Models\UserGroup;
use App\Support\ApiRequestContext;
use App\Support\KnowledgeBasePageData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request, KnowledgeBasePageData $pageData): JsonResponse
    {
        [$user, $canManage, $bases] = $this->context($request);

        return response()->json([
            'data' => $pageData->build($user, $bases, null, null, $canManage),
            'groups' => $this->groups($canManage),
        ]);
    }

    public function show(Request $request, KnowledgeBase $knowledgeBase, KnowledgeBasePageData $pageData): JsonResponse
    {
        [$user, $canManage, $bases] = $this->context($request);

        $knowledgeBase->load([
            'groups:id,name,description',
            'articles' => fn ($query) => $query
                ->when(! $canManage, fn ($articleQuery) => $articleQuery->where('is_published', true))
                ->orderBy('sort_order')
                ->orderBy('title'),
        ]);

        abort_unless($canManage || $knowledgeBase->isVisibleTo($user), 404);

        return response()->json([
            'data' => $pageData->build($user, $bases, $knowledgeBase, null, $canManage),
            'groups' => $this->groups($canManage),
        ]);
    }

    public function showArticle(
        Request $request,
        KnowledgeBase $knowledgeBase,
        KnowledgeBaseArticle $knowledgeBaseArticle,
        KnowledgeBasePageData $pageData,
    ): JsonResponse {
        abort_unless($knowledgeBaseArticle->knowledge_base_id === $knowledgeBase->id, 404);

        [$user, $canManage, $bases] = $this->context($request);

        $knowledgeBase->load([
            'groups:id,name,description',
            'articles' => fn ($query) => $query
                ->when(! $canManage, fn ($articleQuery) => $articleQuery->where('is_published', true))
                ->orderBy('sort_order')
                ->orderBy('title'),
        ]);

        abort_unless($canManage || $knowledgeBase->isVisibleTo($user), 404);

        if (! $canManage && ! $knowledgeBaseArticle->is_published) {
            abort(404);
        }

        return response()->json([
            'data' => $pageData->build($user, $bases, $knowledgeBase, $knowledgeBaseArticle, $canManage),
            'groups' => $this->groups($canManage),
        ]);
    }

    public function store(StoreKnowledgeBaseRequest $request): JsonResponse
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

        return response()->json([
            'message' => __('ui.knowledge.base_created_success'),
            'data' => (new ApiKnowledgeBaseResource($knowledgeBase->load('groups')->loadCount('articles')))->resolve(),
        ], 201);
    }

    public function update(UpdateKnowledgeBaseRequest $request, KnowledgeBase $knowledgeBase): JsonResponse
    {
        $knowledgeBase->update([
            'title' => $request->validated('title'),
            'slug' => $request->validated('slug'),
            'description' => $request->validated('description'),
            'is_published' => $request->validated('is_published'),
            'updated_by_user_id' => $request->user()?->id,
        ]);

        $knowledgeBase->groups()->sync($request->userGroupIds());

        return response()->json([
            'message' => __('ui.knowledge.base_updated_success'),
            'data' => (new ApiKnowledgeBaseResource($knowledgeBase->fresh()->load('groups')->loadCount('articles')))->resolve(),
        ]);
    }

    public function destroy(KnowledgeBase $knowledgeBase): JsonResponse
    {
        $deletedId = $knowledgeBase->id;
        $knowledgeBase->delete();

        return response()->json([
            'message' => __('ui.knowledge.base_deleted_success'),
            'data' => [
                'id' => $deletedId,
            ],
        ]);
    }

    public function storeArticle(StoreKnowledgeBaseArticleRequest $request, KnowledgeBase $knowledgeBase): JsonResponse
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

        return response()->json([
            'message' => __('ui.knowledge.article_created_success'),
            'data' => (new ApiKnowledgeBaseArticleResource($article))->resolve(),
        ], 201);
    }

    public function updateArticle(
        UpdateKnowledgeBaseArticleRequest $request,
        KnowledgeBase $knowledgeBase,
        KnowledgeBaseArticle $knowledgeBaseArticle,
    ): JsonResponse {
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

        return response()->json([
            'message' => __('ui.knowledge.article_updated_success'),
            'data' => (new ApiKnowledgeBaseArticleResource($knowledgeBaseArticle->fresh()))->resolve(),
        ]);
    }

    public function destroyArticle(KnowledgeBase $knowledgeBase, KnowledgeBaseArticle $knowledgeBaseArticle): JsonResponse
    {
        abort_unless($knowledgeBaseArticle->knowledge_base_id === $knowledgeBase->id, 404);

        $deletedId = $knowledgeBaseArticle->id;
        $knowledgeBaseArticle->delete();

        return response()->json([
            'message' => __('ui.knowledge.article_deleted_success'),
            'data' => [
                'id' => $deletedId,
            ],
        ]);
    }

    /**
     * @return array{0: \App\Models\User, 1: bool, 2: \Illuminate\Database\Eloquent\Collection<int, KnowledgeBase>}
     */
    private function context(Request $request): array
    {
        $user = ApiRequestContext::subject($request);
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

        return [$user, $canManage, $bases];
    }

    /**
     * @return array<int, array{id: int, name: string, display_name: string}>
     */
    private function groups(bool $canManage): array
    {
        if (! $canManage) {
            return [];
        }

        return UserGroup::query()
            ->select(['id', 'name', 'description'])
            ->orderBy('name')
            ->get()
            ->map(fn (UserGroup $group): array => [
                'id' => $group->id,
                'name' => $group->name,
                'display_name' => $group->displayName(),
            ])
            ->values()
            ->all();
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
