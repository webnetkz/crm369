<?php

namespace App\Support;

use App\Models\KnowledgeBase;
use App\Models\MenuItem;
use App\Models\PortalSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class NavigationMenuData
{
    /** @var list<string>|null */
    private ?array $tableNames = null;

    /**
     * Build the personal navigation menu shared by the web sidebar and the
     * native mobile application.
     *
     * @return array{
     *     hiddenItems: array<int, string>,
     *     customItems: array<int, array{id: int, title: string, icon: string|null, url: string, opensInNewTab: bool}>,
     *     knowledgeBases: array<int, array{id: int, title: string}>,
     *     order: array<int, string>
     * }
     */
    public function build(?User $user): array
    {
        if (! $this->hasTable('menu_items') || ! $user) {
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
            ! PortalSetting::current()->isModuleEnabled('knowledge-bases')
            || ! $user->canAccessKnowledgeBases()
            || ! $this->hasTable('knowledge_bases')
            || ! $this->hasTable('knowledge_base_group')
            || ! $this->hasTable('user_groups')
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

    private function hasTable(string $table): bool
    {
        $this->tableNames ??= array_values(Schema::getTableListing(null, false));

        return in_array($table, $this->tableNames, true);
    }
}
