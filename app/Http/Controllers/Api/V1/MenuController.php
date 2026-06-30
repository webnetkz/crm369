<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreMenuItemRequest;
use App\Http\Requests\Settings\UpdateMenuItemVisibilityRequest;
use App\Http\Resources\ApiMenuItemResource;
use App\Models\MenuItem;
use App\Support\ApiRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = ApiRequestContext::subject($request);
        $hiddenBuiltInKeys = MenuItem::hiddenBuiltInKeysForUser($user);

        return response()->json([
            'can' => [
                'share_with_all_users' => $user->canViewUsers(),
            ],
            'data' => [
                'built_in_items' => collect(MenuItem::builtInDefinitions())
                    ->map(function (array $definition, string $key) use ($hiddenBuiltInKeys): array {
                        return [
                            'key' => $key,
                            'title' => __($definition['title_key']),
                            'url' => $definition['url'],
                            'is_visible' => ! in_array($key, $hiddenBuiltInKeys, true),
                        ];
                    })
                    ->values()
                    ->all(),
                'custom_items' => MenuItem::customItemsForSettingsUser($user)
                    ->map(fn (MenuItem $item): array => $this->serializeMenuItem($item, $user))
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function store(StoreMenuItemRequest $request): JsonResponse
    {
        $user = ApiRequestContext::subject($request);
        $shareWithAllUsers = $request->shareWithAllUsers();
        $isVisible = $request->boolean('is_visible');
        $menuItem = MenuItem::create([
            'type' => MenuItem::TYPE_CUSTOM,
            'user_id' => $user->id,
            'is_global' => $shareWithAllUsers,
            'title' => $request->validated('title'),
            'icon' => $request->validated('icon'),
            'url' => $request->validated('url'),
            'opens_in_new_tab' => $request->boolean('opens_in_new_tab'),
            'is_visible' => $shareWithAllUsers ? true : $isVisible,
            'sort_order' => $this->nextCustomSortOrder(),
        ]);

        if ($shareWithAllUsers && ! $isVisible) {
            $hiddenIds = collect($user->hiddenMenuItemIds())
                ->push($menuItem->id)
                ->unique()
                ->values()
                ->all();

            $user->update([
                'hidden_menu_item_ids' => $hiddenIds,
            ]);
        }

        return response()->json([
            'message' => __('ui.menu.created_success'),
            'data' => (new ApiMenuItemResource($menuItem))->resolve(),
        ], 201);
    }

    public function update(StoreMenuItemRequest $request, MenuItem $menuItem): JsonResponse
    {
        abort_if($menuItem->isBuiltIn(), 404);

        $user = ApiRequestContext::subject($request);

        $this->authorizeCustomItemMutation($menuItem, $user);

        $shareWithAllUsers = $request->shareWithAllUsers();
        $isVisible = $request->boolean('is_visible');

        $menuItem->update([
            'user_id' => $shareWithAllUsers ? $menuItem->user_id : $user->id,
            'is_global' => $shareWithAllUsers,
            'title' => $request->validated('title'),
            'icon' => $request->validated('icon'),
            'url' => $request->validated('url'),
            'opens_in_new_tab' => $request->boolean('opens_in_new_tab'),
            'is_visible' => $shareWithAllUsers ? true : $isVisible,
        ]);

        $this->syncHiddenSharedMenuItemState(
            $user,
            $menuItem,
            $shareWithAllUsers,
            $isVisible,
        );

        return response()->json([
            'message' => __('ui.menu.updated_success'),
            'data' => $this->serializeMenuItem($menuItem->fresh(), $user),
        ]);
    }

    public function updateBuiltInVisibility(UpdateMenuItemVisibilityRequest $request, string $key): JsonResponse
    {
        $definition = MenuItem::builtInDefinition($key);
        abort_if($definition === null, 404);

        $user = ApiRequestContext::subject($request);
        $hiddenKeys = collect($user->hiddenMenuItemKeys());

        if ($request->isVisible()) {
            $hiddenKeys = $hiddenKeys->reject(fn (string $hiddenKey): bool => $hiddenKey === $key);
        } else {
            $hiddenKeys->push($key);
        }

        $resolvedKeys = $hiddenKeys->unique()->values()->all();

        $user->update([
            'hidden_menu_item_keys' => $resolvedKeys,
        ]);

        return response()->json([
            'message' => __('ui.menu.visibility_updated_success'),
            'data' => [
                'key' => $key,
                'is_visible' => $request->isVisible(),
            ],
        ]);
    }

    public function updateVisibility(UpdateMenuItemVisibilityRequest $request, MenuItem $menuItem): JsonResponse
    {
        abort_if($menuItem->isBuiltIn(), 404);
        $user = ApiRequestContext::subject($request);

        if ($menuItem->is_global) {
            $hiddenIds = collect($user->hiddenMenuItemIds());

            if ($request->isVisible()) {
                $hiddenIds = $hiddenIds->reject(fn (int $hiddenId): bool => $hiddenId === $menuItem->id);
            } else {
                $hiddenIds->push($menuItem->id);
            }

            $user->update([
                'hidden_menu_item_ids' => $hiddenIds->unique()->values()->all(),
            ]);
        } else {
            abort_unless($menuItem->user_id === $user->id, 403);

            $menuItem->update([
                'is_visible' => $request->isVisible(),
            ]);
        }

        return response()->json([
            'message' => __('ui.menu.visibility_updated_success'),
            'data' => $this->serializeMenuItem($menuItem->fresh(), $user),
        ]);
    }

    public function destroy(Request $request, MenuItem $menuItem): JsonResponse
    {
        if ($menuItem->isBuiltIn()) {
            return response()->json([
                'message' => __('ui.menu.cannot_delete_system'),
            ], 422);
        }

        $user = ApiRequestContext::subject($request);

        if ($menuItem->is_global) {
            abort_unless($user->canViewUsers(), 403);
        } else {
            abort_unless($menuItem->user_id === $user->id, 403);
        }

        $deletedId = $menuItem->id;
        $menuItem->delete();

        return response()->json([
            'message' => __('ui.menu.deleted_success'),
            'data' => [
                'id' => $deletedId,
            ],
        ]);
    }

    private function serializeMenuItem(MenuItem $item, $user): array
    {
        $isVisible = $item->is_global
            ? ! in_array($item->id, $user?->hiddenMenuItemIds() ?? [], true)
            : $item->is_visible;

        return [
            ...(new ApiMenuItemResource($item))->resolve(),
            'is_visible' => $isVisible,
        ];
    }

    private function nextCustomSortOrder(): int
    {
        return ((int) MenuItem::query()->custom()->max('sort_order')) + 10;
    }

    private function authorizeCustomItemMutation(MenuItem $menuItem, $user): void
    {
        if ($menuItem->is_global) {
            abort_unless($user?->canViewUsers() ?? false, 403);

            return;
        }

        abort_unless($menuItem->user_id === $user?->id, 403);
    }

    private function syncHiddenSharedMenuItemState(
        $user,
        MenuItem $menuItem,
        bool $isGlobal,
        bool $isVisible,
    ): void {
        if (! $user) {
            return;
        }

        $hiddenIds = collect($user->hiddenMenuItemIds())
            ->reject(fn (int $hiddenId): bool => $hiddenId === $menuItem->id);

        if ($isGlobal && ! $isVisible) {
            $hiddenIds->push($menuItem->id);
        }

        $user->update([
            'hidden_menu_item_ids' => $hiddenIds->unique()->values()->all(),
        ]);
    }
}
