<?php

namespace App\Http\Middleware;

use App\Models\KnowledgeBase;
use App\Models\MenuItem;
use App\Models\PortalSetting;
use App\Models\User;
use App\Support\ChatSidebarData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $impersonator = $this->impersonator($request);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user()
                    ? $this->serializeAuthUser($request->user())
                    : null,
                'isSuperAdmin' => $request->user()?->isSuperAdmin() ?? false,
                'canViewUsers' => $request->user()?->canViewUsers() ?? false,
                'canImpersonateUsers' => $request->user()?->canImpersonateUsers() ?? false,
                'canManageApiTokens' => $request->user()?->canManageApiTokens() ?? false,
                'canManageWebhooks' => $request->user()?->canManageWebhooks() ?? false,
                'canManageKnowledgeBases' => $request->user()?->canManageKnowledgeBases() ?? false,
                'canAccessFunnels' => $this->crmFunnelsEnabled() ? ($request->user()?->canAccessFunnels() ?? false) : false,
                'canManageFunnels' => $this->crmFunnelsEnabled() ? ($request->user()?->canManageFunnels() ?? false) : false,
                'isImpersonating' => $impersonator !== null,
                'impersonator' => $impersonator
                    ? [
                        'id' => $impersonator->id,
                        'name' => $impersonator->name,
                        'email' => $impersonator->email,
                    ]
                    : null,
            ],
            'portal' => function (): array {
                $portal = PortalSetting::current();

                return [
                    'companyName' => $portal->companyName(),
                    'logoUrl' => $portal->logoUrl(),
                    'defaultLanguage' => $portal->defaultLanguage(),
                ];
            },
            'menu' => fn (): array => $this->menuProps(),
            'chat' => fn (): array => $this->chatProps($request),
            'notifications' => fn (): array => $this->notificationProps($request),
            'locale' => [
                'current' => App::currentLocale(),
                'messages' => [
                    'ru' => Lang::get('ui', [], 'ru'),
                    'en' => Lang::get('ui', [], 'en'),
                ],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{unreadCount: int}
     */
    private function chatProps(Request $request): array
    {
        $user = $request->user();

        if (
            ! $user
            || ! Schema::hasTable('chat_conversations')
            || ! Schema::hasTable('chat_conversation_participants')
            || ! Schema::hasTable('chat_messages')
        ) {
            return [
                'unreadCount' => 0,
            ];
        }

        return app(ChatSidebarData::class)->shared($user);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAuthUser(User $user): array
    {
        $group = $user->relationLoaded('group') ? $user->group : $user->group()->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'background_color' => $user->background_color,
            'background_image' => $user->background_image,
            'background_blur' => $user->background_blur,
            'avatar_position_x' => $user->avatar_position_x,
            'avatar_position_y' => $user->avatar_position_y,
            'avatar_scale' => $user->avatar_scale,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'language' => $user->resolvedLanguage(),
            'has_selected_language' => $user->hasSelectedLanguage(),
            'is_super_admin' => $user->isSuperAdmin(),
            'group' => $group
                ? [
                    'id' => $group->id,
                    'name' => $group->name,
                    'display_name' => $group->displayName(),
                ]
                : null,
            'user_group_id' => $user->user_group_id,
            'is_active' => $user->is_active,
            'deactivated_at' => $user->deactivated_at?->toISOString(),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array{
     *     hiddenItems: array<int, string>,
     *     customItems: array<int, array{id: int, title: string, icon: string|null, url: string, opensInNewTab: bool}>,
     *     knowledgeBases: array<int, array{id: int, title: string}>,
     *     order: array<int, string>
     * }
     */
    private function menuProps(): array
    {
        $user = request()->user();

        if (! Schema::hasTable('menu_items') || ! $user) {
            return [
                'hiddenItems' => [],
                'customItems' => [],
                'knowledgeBases' => [],
                'order' => [],
            ];
        }

        return [
            'hiddenItems' => MenuItem::hiddenBuiltInKeysForUser($user),
            'customItems' => MenuItem::visibleCustomItemsForUser($user)
                ->map(fn (MenuItem $item): array => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'icon' => $item->icon,
                    'url' => $item->url,
                    'opensInNewTab' => $item->opens_in_new_tab,
                ])
                ->values()
                ->all(),
            'knowledgeBases' => $this->knowledgeBaseMenuItems($user),
            'order' => $user->menuItemOrder(),
        ];
    }

    /**
     * @return array<int, array{id: int, title: string}>
     */
    private function knowledgeBaseMenuItems(User $user): array
    {
        if (
            ! Schema::hasTable('knowledge_bases')
            || ! Schema::hasTable('knowledge_base_group')
            || ! Schema::hasTable('user_groups')
        ) {
            return [];
        }

        $canManage = $user->canManageKnowledgeBases();

        return KnowledgeBase::query()
            ->select(['id', 'title'])
            ->when(! $canManage, fn (Builder $query) => $query
                ->where('is_published', true)
                ->where(function (Builder $visibilityQuery) use ($user): void {
                    $visibilityQuery->whereDoesntHave('groups');

                    if (is_numeric($user->user_group_id)) {
                        $visibilityQuery->orWhereHas('groups', fn (Builder $groupQuery) => $groupQuery->where('user_groups.id', (int) $user->user_group_id));
                    }
                }))
            ->orderBy('title')
            ->get()
            ->map(fn (KnowledgeBase $knowledgeBase): array => [
                'id' => $knowledgeBase->id,
                'title' => $knowledgeBase->title,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{unreadCount: int, items: array<int, array{id: string, title: string, message: string, actionUrl: string|null, actionLabel: string|null, createdAt: string|null, isRead: bool}>}
     */
    private function notificationProps(Request $request): array
    {
        $user = $request->user();

        if (! $user || ! Schema::hasTable('notifications')) {
            return [
                'unreadCount' => 0,
                'items' => [],
            ];
        }

        return [
            'unreadCount' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()
                ->limit(20)
                ->get()
                ->map(fn (DatabaseNotification $notification): array => [
                    'id' => $notification->id,
                    'title' => (string) data_get($notification->data, 'title', __('ui.notifications.default_title')),
                    'message' => (string) data_get($notification->data, 'message', ''),
                    'actionUrl' => data_get($notification->data, 'action_url'),
                    'actionLabel' => data_get($notification->data, 'action_label'),
                    'createdAt' => $notification->created_at?->toISOString(),
                    'isRead' => $notification->read_at !== null,
                ])
                ->values()
                ->all(),
        ];
    }

    private function impersonator(Request $request): ?User
    {
        $impersonatorId = $request->session()->get('impersonator_id');

        if (! is_numeric($impersonatorId)) {
            return null;
        }

        return User::query()
            ->select(['id', 'name', 'email'])
            ->find((int) $impersonatorId);
    }

    private function crmFunnelsEnabled(): bool
    {
        return Schema::hasTable('crm_funnels')
            && Schema::hasTable('crm_funnel_user_group');
    }
}
