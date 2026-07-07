<?php

namespace App\Models;

use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string|null $key
 * @property string $type
 * @property int|null $user_id
 * @property bool $is_global
 * @property string $title
 * @property string|null $icon
 * @property string $url
 * @property bool $opens_in_new_tab
 * @property bool $is_visible
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'type', 'user_id', 'is_global', 'title', 'icon', 'url', 'opens_in_new_tab', 'is_visible', 'sort_order'])]
class MenuItem extends Model
{
    public const string TYPE_BUILT_IN = 'built_in';

    public const string TYPE_CUSTOM = 'custom';

    public const string SIDEBAR_SETTINGS_KEY = 'settings';

    public const string DEFAULT_CUSTOM_ICON = 'link';

    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'is_global' => 'boolean',
            'opens_in_new_tab' => 'boolean',
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return array<string, array{label_key: string}>
     */
    public static function availableIcons(): array
    {
        return [
            'link' => ['label_key' => 'ui.menu.icons.link'],
            'globe' => ['label_key' => 'ui.menu.icons.globe'],
            'book' => ['label_key' => 'ui.menu.icons.book'],
            'folder' => ['label_key' => 'ui.menu.icons.folder'],
            'dashboard' => ['label_key' => 'ui.menu.icons.dashboard'],
            'grid' => ['label_key' => 'ui.menu.icons.grid'],
            'clipboard' => ['label_key' => 'ui.menu.icons.clipboard'],
            'message' => ['label_key' => 'ui.menu.icons.message'],
            'news' => ['label_key' => 'ui.menu.icons.news'],
            'tasks' => ['label_key' => 'ui.menu.icons.tasks'],
            'bell' => ['label_key' => 'ui.menu.icons.bell'],
            'shield' => ['label_key' => 'ui.menu.icons.shield'],
            'rocket' => ['label_key' => 'ui.menu.icons.rocket'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function availableIconKeys(): array
    {
        return array_keys(self::availableIcons());
    }

    /**
     * @return array<string, array{title_key: string, fallback_title: string, url: string, sort_order: int}>
     */
    public static function builtInDefinitions(): array
    {
        return [
            'news' => [
                'title_key' => 'ui.news.title',
                'fallback_title' => 'News',
                'url' => '/news',
                'sort_order' => 10,
            ],
            'notifications' => [
                'title_key' => 'ui.notifications.panel_title',
                'fallback_title' => 'Notifications',
                'url' => '/notifications',
                'sort_order' => 20,
            ],
            'dashboard' => [
                'title_key' => 'ui.common.dashboard',
                'fallback_title' => 'Dashboard',
                'url' => '/dashboard',
                'sort_order' => 30,
            ],
            'projects' => [
                'title_key' => 'ui.projects.title',
                'fallback_title' => 'Tasks & projects',
                'url' => '/projects',
                'sort_order' => 40,
            ],
            'chats' => [
                'title_key' => 'ui.chat.title',
                'fallback_title' => 'Chats',
                'url' => '/chats',
                'sort_order' => 50,
            ],
            'knowledge-bases' => [
                'title_key' => 'ui.knowledge.title',
                'fallback_title' => 'Knowledge base',
                'url' => '/knowledge-bases',
                'sort_order' => 60,
            ],
            'funnels' => [
                'title_key' => 'ui.funnels.title',
                'fallback_title' => 'Funnels',
                'url' => '/funnels',
                'sort_order' => 70,
            ],
            'forms' => [
                'title_key' => 'ui.forms.title',
                'fallback_title' => 'Forms',
                'url' => '/forms',
                'sort_order' => 80,
            ],
            'contacts' => [
                'title_key' => 'ui.contacts.title',
                'fallback_title' => 'Contacts',
                'url' => '/contacts',
                'sort_order' => 90,
            ],
            'edo' => [
                'title_key' => 'ui.edo.title',
                'fallback_title' => 'Document signing',
                'url' => '/edo',
                'sort_order' => 95,
            ],
            'production' => [
                'title_key' => 'ui.production.title',
                'fallback_title' => 'Production',
                'url' => '/production',
                'sort_order' => 100,
            ],
            'warehouses' => [
                'title_key' => 'ui.warehouses.title',
                'fallback_title' => 'Warehouses',
                'url' => '/warehouses',
                'sort_order' => 102,
            ],
            'tsd' => [
                'title_key' => 'ui.tsd.title',
                'fallback_title' => 'TSD',
                'url' => '/tsd',
                'sort_order' => 103,
            ],
            'equipment' => [
                'title_key' => 'ui.equipment.title',
                'fallback_title' => 'Equipment',
                'url' => '/equipment',
                'sort_order' => 105,
            ],
            'settings.profile' => [
                'title_key' => 'ui.settings.profile',
                'fallback_title' => 'Profile',
                'url' => '/settings/profile',
                'sort_order' => 110,
            ],
            'settings.security' => [
                'title_key' => 'ui.settings.security',
                'fallback_title' => 'Security',
                'url' => '/settings/security',
                'sort_order' => 120,
            ],
            'settings.appearance' => [
                'title_key' => 'ui.settings.appearance',
                'fallback_title' => 'Appearance',
                'url' => '/settings/appearance',
                'sort_order' => 130,
            ],
            'settings.users' => [
                'title_key' => 'ui.settings.users',
                'fallback_title' => 'Users',
                'url' => '/settings/users',
                'sort_order' => 140,
            ],
            'settings.groups' => [
                'title_key' => 'ui.settings.groups',
                'fallback_title' => 'Groups',
                'url' => '/settings/groups',
                'sort_order' => 150,
            ],
            'settings.rights' => [
                'title_key' => 'ui.settings.rights',
                'fallback_title' => 'Rights',
                'url' => '/settings/rights',
                'sort_order' => 160,
            ],
            'settings.portal' => [
                'title_key' => 'ui.settings.portal',
                'fallback_title' => 'Portal',
                'url' => '/settings/portal',
                'sort_order' => 170,
            ],
            'settings.modules' => [
                'title_key' => 'ui.settings.modules',
                'fallback_title' => 'Modules',
                'url' => '/settings/modules',
                'sort_order' => 172,
            ],
            'settings.integrations' => [
                'title_key' => 'ui.settings.integrations',
                'fallback_title' => 'Integrations',
                'url' => '/settings/integrations',
                'sort_order' => 175,
            ],
            'settings.api' => [
                'title_key' => 'ui.settings.api',
                'fallback_title' => 'API',
                'url' => '/settings/api',
                'sort_order' => 177,
            ],
            'settings.logs' => [
                'title_key' => 'ui.settings.logs',
                'fallback_title' => 'Logs',
                'url' => '/settings/logs',
                'sort_order' => 178,
            ],
            'settings.webhooks' => [
                'title_key' => 'ui.settings.webhooks',
                'fallback_title' => 'Webhooks',
                'url' => '/settings/webhooks',
                'sort_order' => 180,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function sidebarBuiltInKeys(): array
    {
        return [
            'news',
            'notifications',
            'dashboard',
            'projects',
            'chats',
            'knowledge-bases',
            'funnels',
            'forms',
            'contacts',
            'edo',
            'production',
            'warehouses',
            'tsd',
            'equipment',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function sidebarTopLevelKeys(): array
    {
        return [
            ...self::sidebarBuiltInKeys(),
            self::SIDEBAR_SETTINGS_KEY,
        ];
    }

    /**
     * @param  Builder<MenuItem>  $query
     * @return Builder<MenuItem>
     */
    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_CUSTOM);
    }

    /**
     * @param  Builder<MenuItem>  $query
     * @return Builder<MenuItem>
     */
    public function scopeBuiltIn(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_BUILT_IN);
    }

    /**
     * @param  Builder<MenuItem>  $query
     * @return Builder<MenuItem>
     */
    public function scopeSharedItem(Builder $query): Builder
    {
        return $query->where('is_global', true);
    }

    /**
     * @param  Builder<MenuItem>  $query
     * @return Builder<MenuItem>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * @return Collection<int, MenuItem>
     */
    public static function visibleCustomItemsForUser(User $user): Collection
    {
        return self::query()
            ->custom()
            ->where(function (Builder $query) use ($user): void {
                $query->sharedItem()
                    ->orWhere(fn (Builder $personalQuery): Builder => $personalQuery
                        ->where('is_global', false)
                        ->ownedBy($user));
            })
            ->where(function (Builder $query): void {
                $query->sharedItem()
                    ->orWhere(fn (Builder $personalQuery): Builder => $personalQuery
                        ->where('is_global', false)
                        ->where('is_visible', true));
            })
            ->where(function (Builder $query) use ($user): void {
                $query->where('is_global', false)
                    ->orWhereNotIn('id', $user->hiddenMenuItemIds());
            })
            ->orderByDesc('is_global')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->values();
    }

    /**
     * @return array<int, string>
     */
    public static function hiddenBuiltInKeysForUser(User $user): array
    {
        $legacyHiddenKeys = self::query()
            ->builtIn()
            ->where('is_visible', false)
            ->pluck('key')
            ->filter()
            ->values()
            ->all();

        return collect([...$legacyHiddenKeys, ...$user->hiddenMenuItemKeys()])
            ->merge(PortalSetting::current()->disabledModules())
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, MenuItem>
     */
    public static function customItemsForSettingsUser(User $user): Collection
    {
        return self::query()
            ->custom()
            ->where(function (Builder $query) use ($user): void {
                $query->sharedItem()
                    ->orWhere(fn (Builder $personalQuery): Builder => $personalQuery
                        ->where('is_global', false)
                        ->ownedBy($user));
            })
            ->orderByDesc('is_global')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->values();
    }

    public static function builtInDefinition(string $key): ?array
    {
        return self::builtInDefinitions()[$key] ?? null;
    }

    public function displayTitle(): string
    {
        if ($this->type !== self::TYPE_BUILT_IN || ! is_string($this->key)) {
            return $this->title;
        }

        $definition = self::builtInDefinition($this->key);

        return $definition ? __($definition['title_key']) : $this->title;
    }

    public function isBuiltIn(): bool
    {
        return $this->type === self::TYPE_BUILT_IN;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
