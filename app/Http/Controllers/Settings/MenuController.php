<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreMenuItemRequest;
use App\Http\Requests\Settings\UpdateMenuOrderRequest;
use App\Http\Requests\Settings\UpdateMenuItemVisibilityRequest;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $hiddenBuiltInKeys = MenuItem::hiddenBuiltInKeysForUser($user);

        return Inertia::render('settings/Menu', [
            'can' => [
                'share_with_all_users' => $user->canViewUsers(),
            ],
            'availableIcons' => collect(MenuItem::availableIcons())
                ->map(fn (array $definition, string $key): array => [
                    'value' => $key,
                    'label' => __($definition['label_key']),
                ])
                ->values(),
            'builtInItems' => collect(MenuItem::builtInDefinitions())
                ->map(function (array $definition, string $key) use ($hiddenBuiltInKeys): array {
                    return [
                        'key' => $key,
                        'title' => __($definition['title_key']),
                        'url' => $definition['url'],
                        'is_visible' => ! in_array($key, $hiddenBuiltInKeys, true),
                    ];
                })
                ->values(),
            'customItems' => MenuItem::customItemsForSettingsUser($user)
                ->map(fn (MenuItem $item): array => $this->serializeMenuItem($item))
                ->values(),
        ]);
    }

    public function store(StoreMenuItemRequest $request): RedirectResponse
    {
        $shareWithAllUsers = $request->shareWithAllUsers();
        $isVisible = $request->boolean('is_visible');
        $menuItem = MenuItem::create([
            'type' => MenuItem::TYPE_CUSTOM,
            'user_id' => $request->user()?->id,
            'is_global' => $shareWithAllUsers,
            'title' => $request->validated('title'),
            'icon' => $request->validated('icon'),
            'url' => $request->validated('url'),
            'opens_in_new_tab' => $request->boolean('opens_in_new_tab'),
            'is_visible' => $shareWithAllUsers ? true : $isVisible,
            'sort_order' => $this->nextCustomSortOrder(),
        ]);

        if ($shareWithAllUsers && ! $isVisible) {
            $hiddenIds = collect($request->user()?->hiddenMenuItemIds() ?? [])
                ->push($menuItem->id)
                ->unique()
                ->values()
                ->all();

            $request->user()?->update([
                'hidden_menu_item_ids' => $hiddenIds,
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.menu.created_success')]);

        return back();
    }

    public function update(StoreMenuItemRequest $request, MenuItem $menuItem): RedirectResponse
    {
        abort_if($menuItem->isBuiltIn(), 404);

        $this->authorizeCustomItemMutation($menuItem, $request->user());

        $shareWithAllUsers = $request->shareWithAllUsers();
        $isVisible = $request->boolean('is_visible');

        $menuItem->update([
            'user_id' => $shareWithAllUsers ? $menuItem->user_id : $request->user()?->id,
            'is_global' => $shareWithAllUsers,
            'title' => $request->validated('title'),
            'icon' => $request->validated('icon'),
            'url' => $request->validated('url'),
            'opens_in_new_tab' => $request->boolean('opens_in_new_tab'),
            'is_visible' => $shareWithAllUsers ? true : $isVisible,
        ]);

        $this->syncHiddenSharedMenuItemState(
            $request->user(),
            $menuItem,
            $shareWithAllUsers,
            $isVisible,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.menu.updated_success')]);

        return back();
    }

    public function updateBuiltInVisibility(UpdateMenuItemVisibilityRequest $request, string $key): RedirectResponse
    {
        $definition = MenuItem::builtInDefinition($key);

        abort_if($definition === null, 404);

        $hiddenKeys = collect($request->user()?->hiddenMenuItemKeys() ?? []);

        if ($request->isVisible()) {
            $hiddenKeys = $hiddenKeys->reject(fn (string $hiddenKey): bool => $hiddenKey === $key);
        } else {
            $hiddenKeys->push($key);
        }

        $request->user()?->update([
            'hidden_menu_item_keys' => $hiddenKeys->unique()->values()->all(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.menu.visibility_updated_success')]);

        return back();
    }

    public function updateVisibility(UpdateMenuItemVisibilityRequest $request, MenuItem $menuItem): RedirectResponse
    {
        abort_if($menuItem->isBuiltIn(), 404);

        if ($menuItem->is_global) {
            $hiddenIds = collect($request->user()?->hiddenMenuItemIds() ?? []);

            if ($request->isVisible()) {
                $hiddenIds = $hiddenIds->reject(fn (int $hiddenId): bool => $hiddenId === $menuItem->id);
            } else {
                $hiddenIds->push($menuItem->id);
            }

            $request->user()?->update([
                'hidden_menu_item_ids' => $hiddenIds->unique()->values()->all(),
            ]);
        } else {
            abort_unless($menuItem->user_id === $request->user()?->id, 403);

            $menuItem->update([
                'is_visible' => $request->isVisible(),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.menu.visibility_updated_success')]);

        return back();
    }

    public function destroy(MenuItem $menuItem): RedirectResponse
    {
        if ($menuItem->isBuiltIn()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('ui.menu.cannot_delete_system')]);

            return back();
        }

        if ($menuItem->is_global) {
            abort_unless($requestUser = request()->user(), 403);
            abort_unless($requestUser->canViewUsers(), 403);
        } else {
            abort_unless($menuItem->user_id === request()->user()?->id, 403);
        }

        $menuItem->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.menu.deleted_success')]);

        return back();
    }

    public function updateOrder(UpdateMenuOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $allowedKeys = $this->allowedMenuOrderKeysFor($user);
        $requestedOrder = collect($request->itemKeys())
            ->filter(fn (string $key): bool => $allowedKeys->contains($key))
            ->unique()
            ->values();

        $preservedOrder = collect($user->menuItemOrder())
            ->filter(fn (string $key): bool => $this->isSidebarOrderKey($key))
            ->reject(fn (string $key): bool => $requestedOrder->contains($key))
            ->values();

        $resolvedOrder = $requestedOrder
            ->merge($preservedOrder)
            ->unique()
            ->values()
            ->all();

        $user->update([
            'menu_item_order' => $resolvedOrder,
        ]);

        return response()->json([
            'order' => $resolvedOrder,
        ]);
    }

    private function authorizeCustomItemMutation(MenuItem $menuItem, ?User $user): void
    {
        if ($menuItem->is_global) {
            abort_unless($user?->canViewUsers() ?? false, 403);

            return;
        }

        abort_unless($menuItem->user_id === $user?->id, 403);
    }

    private function syncHiddenSharedMenuItemState(
        ?User $user,
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

    /**
     * @return array{id: int, title: string, icon: string|null, url: string, opens_in_new_tab: bool, is_global: bool, is_visible: bool}
     */
    private function serializeMenuItem(MenuItem $item): array
    {
        $user = request()->user();
        $isVisible = $item->is_global
            ? ! in_array($item->id, $user?->hiddenMenuItemIds() ?? [], true)
            : $item->is_visible;

        return [
            'id' => $item->id,
            'title' => $item->title,
            'icon' => $item->icon,
            'url' => $item->url,
            'opens_in_new_tab' => $item->opens_in_new_tab,
            'is_global' => $item->is_global,
            'is_visible' => $isVisible,
        ];
    }

    private function nextCustomSortOrder(): int
    {
        return ((int) MenuItem::query()->custom()->max('sort_order')) + 10;
    }

    /**
     * @return Collection<int, string>
     */
    private function allowedMenuOrderKeysFor(User $user): Collection
    {
        return collect(MenuItem::sidebarTopLevelKeys())
            ->merge(
                MenuItem::visibleCustomItemsForUser($user)
                    ->map(fn (MenuItem $item): string => sprintf('custom:%d', $item->id)),
            )
            ->unique()
            ->values();
    }

    private function isSidebarOrderKey(string $key): bool
    {
        return in_array($key, MenuItem::sidebarTopLevelKeys(), true)
            || preg_match('/^custom:\d+$/', $key) === 1;
    }
}
