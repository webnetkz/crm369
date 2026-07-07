<?php

use App\Models\MenuItem;
use App\Models\User;
use App\Models\UserGroup;
use Inertia\Testing\AssertableInertia as Assert;

function administratorsGroup(): UserGroup
{
    return UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);
}

test('menu settings page is available to authenticated users and admins can share items', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $user = User::factory()->create();
    $admin = User::factory()->create([
        'user_group_id' => administratorsGroup()->id,
    ]);
    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $this->actingAs($user)
        ->get(route('settings.menu.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Menu')
            ->where('can.share_with_all_users', false)
            ->has('availableIcons')
            ->has('builtInItems')
            ->has('customItems')
        );

    $this->actingAs($admin)
        ->get(route('settings.menu.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.share_with_all_users', true)
        );

    $this->actingAs($superAdmin)
        ->get(route('settings.menu.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.share_with_all_users', true)
        );
});

test('users can create personal custom menu items', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('settings.menu.store'), [
            'title' => 'My Notes',
            'icon' => 'globe',
            'url' => '/notes',
            'opens_in_new_tab' => false,
            'is_visible' => true,
            'is_global' => true,
        ])
        ->assertRedirect();

    $menuItem = MenuItem::query()->where('title', 'My Notes')->first();

    $this->assertModelExists($menuItem);

    expect($menuItem->type)->toBe(MenuItem::TYPE_CUSTOM)
        ->and($menuItem->user_id)->toBe($user->id)
        ->and($menuItem->icon)->toBe('globe')
        ->and($menuItem->is_global)->toBeFalse()
        ->and($menuItem->is_visible)->toBeTrue();
});

test('custom menu items are visible by default when visibility is omitted', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('settings.menu.store'), [
            'title' => 'Default Visible',
            'url' => '/default-visible',
            'opens_in_new_tab' => false,
            'is_global' => false,
        ])
        ->assertRedirect();

    $menuItem = MenuItem::query()->where('title', 'Default Visible')->firstOrFail();

    expect($menuItem->is_visible)->toBeTrue();
});

test('admins can create shared custom menu items', function () {
    $admin = User::factory()->create([
        'user_group_id' => administratorsGroup()->id,
    ]);

    $this->actingAs($admin)
        ->post(route('settings.menu.store'), [
            'title' => 'Docs',
            'url' => 'https://docs.example.com',
            'opens_in_new_tab' => true,
            'is_visible' => true,
            'is_global' => true,
        ])
        ->assertRedirect();

    $menuItem = MenuItem::query()->where('title', 'Docs')->first();

    $this->assertModelExists($menuItem);

    expect($menuItem->user_id)->toBe($admin->id)
        ->and($menuItem->is_global)->toBeTrue()
        ->and($menuItem->opens_in_new_tab)->toBeTrue();
});

test('users can update their personal custom menu items', function () {
    $user = User::factory()->create();
    $menuItem = MenuItem::factory()->create([
        'user_id' => $user->id,
        'is_global' => false,
        'is_visible' => true,
        'title' => 'Before edit',
        'icon' => 'link',
        'url' => '/before-edit',
        'opens_in_new_tab' => false,
    ]);

    $this->actingAs($user)
        ->patch(route('settings.menu.items.update', $menuItem), [
            'title' => 'After edit',
            'icon' => 'rocket',
            'url' => '/after-edit',
            'opens_in_new_tab' => true,
            'is_visible' => false,
            'is_global' => false,
        ])
        ->assertRedirect();

    expect($menuItem->fresh()->title)->toBe('After edit')
        ->and($menuItem->fresh()->icon)->toBe('rocket')
        ->and($menuItem->fresh()->url)->toBe('/after-edit')
        ->and($menuItem->fresh()->opens_in_new_tab)->toBeTrue()
        ->and($menuItem->fresh()->is_visible)->toBeFalse()
        ->and($menuItem->fresh()->is_global)->toBeFalse();
});

test('only admins can update shared custom menu items', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create([
        'user_group_id' => administratorsGroup()->id,
    ]);

    $menuItem = MenuItem::factory()->create([
        'user_id' => $admin->id,
        'is_global' => true,
        'is_visible' => true,
        'title' => 'Shared before edit',
        'icon' => 'link',
    ]);

    $this->actingAs($user)
        ->patch(route('settings.menu.items.update', $menuItem), [
            'title' => 'Blocked edit',
            'icon' => 'shield',
            'url' => '/blocked-edit',
            'opens_in_new_tab' => false,
            'is_visible' => true,
            'is_global' => true,
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->patch(route('settings.menu.items.update', $menuItem), [
            'title' => 'Shared after edit',
            'icon' => 'shield',
            'url' => '/shared-after-edit',
            'opens_in_new_tab' => true,
            'is_visible' => false,
            'is_global' => true,
        ])
        ->assertRedirect();

    expect($menuItem->fresh()->title)->toBe('Shared after edit')
        ->and($menuItem->fresh()->icon)->toBe('shield')
        ->and($menuItem->fresh()->url)->toBe('/shared-after-edit')
        ->and($menuItem->fresh()->opens_in_new_tab)->toBeTrue()
        ->and($menuItem->fresh()->is_global)->toBeTrue()
        ->and($menuItem->fresh()->is_visible)->toBeTrue()
        ->and($admin->fresh()->hiddenMenuItemIds())->toContain($menuItem->id);
});

test('shared custom items created as hidden stay hidden only for the creator and remain visible to other users', function () {
    $admin = User::factory()->create([
        'user_group_id' => administratorsGroup()->id,
    ]);
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('settings.menu.store'), [
            'title' => 'Shared Handbook',
            'url' => '/handbook',
            'opens_in_new_tab' => false,
            'is_visible' => false,
            'is_global' => true,
        ])
        ->assertRedirect();

    $menuItem = MenuItem::query()->where('title', 'Shared Handbook')->firstOrFail();

    expect($menuItem->is_global)->toBeTrue()
        ->and($menuItem->is_visible)->toBeTrue()
        ->and($admin->refresh()->hiddenMenuItemIds())->toContain($menuItem->id);

    $adminTitles = collect(
        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->inertiaProps('menu.customItems'),
    )->pluck('title');

    $userTitles = collect(
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->inertiaProps('menu.customItems'),
    )->pluck('title');

    expect($adminTitles->all())->not->toContain('Shared Handbook')
        ->and($userTitles->all())->toContain('Shared Handbook');
});

test('menu item urls must be absolute or internal paths', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('settings.menu.store'), [
            'title' => 'Broken link',
            'url' => 'example.com',
            'opens_in_new_tab' => false,
            'is_visible' => true,
            'is_global' => false,
        ])
        ->assertSessionHasErrors('url');
});

test('users can hide built in menu items only for themselves', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('settings.menu.built-in.visibility.update', 'dashboard'), [
            'is_visible' => false,
        ])
        ->assertRedirect();

    expect($user->refresh()->hiddenMenuItemKeys())->toContain('dashboard')
        ->and($otherUser->refresh()->hiddenMenuItemKeys())->not->toContain('dashboard');

    $hiddenItems = $this->actingAs($user)
        ->get(route('dashboard'))
        ->inertiaProps('menu.hiddenItems');

    $otherHiddenItems = $this->actingAs($otherUser)
        ->get(route('dashboard'))
        ->inertiaProps('menu.hiddenItems');

    expect($hiddenItems)->toContain('dashboard')
        ->and($otherHiddenItems)->not->toContain('dashboard');
});

test('users can save personal sidebar menu order', function () {
    $user = User::factory()->create();
    $menuItem = MenuItem::factory()->create([
        'user_id' => $user->id,
        'title' => 'My Link',
        'url' => '/my-link',
        'is_global' => false,
        'is_visible' => true,
    ]);

    $expectedOrder = [
        'projects',
        sprintf('custom:%d', $menuItem->id),
        'dashboard',
        'settings',
    ];

    $this->actingAs($user)
        ->patchJson(route('settings.menu.order.update'), [
            'items' => [
                'projects',
                sprintf('custom:%d', $menuItem->id),
                'dashboard',
                'unknown-item',
                'settings',
                'dashboard',
            ],
        ])
        ->assertOk()
        ->assertJson([
            'order' => $expectedOrder,
        ]);

    expect($user->refresh()->menuItemOrder())->toBe($expectedOrder);

    $sharedOrder = $this->actingAs($user)
        ->get(route('dashboard'))
        ->inertiaProps('menu.order');

    expect($sharedOrder)->toBe($expectedOrder);
});

test('sidebar menu order is stored separately for each user in database', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $expectedOrder = [
        'projects',
        'news',
        'dashboard',
        'settings',
    ];

    $this->actingAs($user)
        ->patchJson(route('settings.menu.order.update'), [
            'items' => $expectedOrder,
        ])
        ->assertOk()
        ->assertJson([
            'order' => $expectedOrder,
        ]);

    expect($user->refresh()->menuItemOrder())->toBe($expectedOrder)
        ->and($otherUser->refresh()->menuItemOrder())->toBe([]);

    $userSharedOrder = $this->actingAs($user)
        ->get(route('dashboard'))
        ->inertiaProps('menu.order');

    $otherUserSharedOrder = $this->actingAs($otherUser)
        ->get(route('dashboard'))
        ->inertiaProps('menu.order');

    expect($userSharedOrder)->toBe($expectedOrder)
        ->and($otherUserSharedOrder)->toBe([]);
});

test('shared custom items are visible to everyone and can be hidden per user', function () {
    $admin = User::factory()->create([
        'user_group_id' => administratorsGroup()->id,
    ]);
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $menuItem = MenuItem::factory()->create([
        'user_id' => $admin->id,
        'title' => 'Knowledge Base',
        'url' => '/knowledge-base',
        'is_global' => true,
        'is_visible' => true,
    ]);

    $initialTitles = collect(
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->inertiaProps('menu.customItems'),
    )->pluck('title');

    expect($initialTitles->all())->toContain('Knowledge Base');

    $this->actingAs($user)
        ->patch(route('settings.menu.items.visibility.update', $menuItem), [
            'is_visible' => false,
        ])
        ->assertRedirect();

    expect($user->refresh()->hiddenMenuItemIds())->toContain($menuItem->id)
        ->and($otherUser->refresh()->hiddenMenuItemIds())->not->toContain($menuItem->id);

    $hiddenTitles = collect(
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->inertiaProps('menu.customItems'),
    )->pluck('title');

    $otherTitles = collect(
        $this->actingAs($otherUser)
            ->get(route('dashboard'))
            ->inertiaProps('menu.customItems'),
    )->pluck('title');

    expect($hiddenTitles->all())->not->toContain('Knowledge Base')
        ->and($otherTitles->all())->toContain('Knowledge Base');
});

test('users can toggle and delete their personal custom menu items', function () {
    $user = User::factory()->create();

    $menuItem = MenuItem::factory()->create([
        'user_id' => $user->id,
        'is_global' => false,
        'is_visible' => true,
    ]);

    $this->actingAs($user)
        ->patch(route('settings.menu.items.visibility.update', $menuItem), [
            'is_visible' => false,
        ])
        ->assertRedirect();

    expect($menuItem->refresh()->is_visible)->toBeFalse();

    $this->actingAs($user)
        ->delete(route('settings.menu.items.destroy', $menuItem))
        ->assertRedirect();

    $this->assertModelMissing($menuItem);
});

test('only admins can delete shared custom menu items', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create([
        'user_group_id' => administratorsGroup()->id,
    ]);

    $menuItem = MenuItem::factory()->create([
        'user_id' => $admin->id,
        'is_global' => true,
    ]);

    $this->actingAs($user)
        ->delete(route('settings.menu.items.destroy', $menuItem))
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('settings.menu.items.destroy', $menuItem))
        ->assertRedirect();

    $this->assertModelMissing($menuItem);
});

test('menu form keeps visibility enabled by default and nav opens flagged items in a new tab', function () {
    $menuPage = file_get_contents(resource_path('js/pages/settings/Menu.vue'));
    $navMain = file_get_contents(resource_path('js/components/NavMain.vue'));
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

    expect($menuPage)->toContain('is_visible: true')
        ->and($menuPage)->toContain('createMenuItemDefaults')
        ->and($menuPage)->toContain('editMenuItemDefaults')
        ->and($menuPage)->toContain('icon: \'link\'')
        ->and($menuPage)->toContain('const editingItemId = ref<number | null>(null);')
        ->and($menuPage)->toContain('const editDialogOpen = ref(false);')
        ->and($menuPage)->toContain('editForm.patch(updateMenuItem.url(editingItemId.value), {')
        ->and($menuPage)->toContain('@click="startEditing(item)"')
        ->and($menuPage)->toContain('<Dialog')
        ->and($menuPage)->toContain(':open="editDialogOpen"')
        ->and($menuPage)->toContain('t.menu.icon_label')
        ->and($menuPage)->toContain('resolveMenuIcon(item.icon)')
        ->and($menuPage)->toContain('editForm.is_visible = editMenuItemDefaults.is_visible')
        ->and($navMain)->toContain(':target="anchorTarget(item)"')
        ->and($navMain)->toContain(':target="anchorTarget(child)"')
        ->and($navMain)->toContain("return item.opensInNewTab ? '_blank' : undefined")
        ->and($navMain)->toContain("return item.opensInNewTab ? 'noopener noreferrer' : undefined")
        ->and($sidebar)->toContain('resolveMenuIcon(item.icon)');
});

test('nav reorder handle appears after two second hover delay and renders six drag dots', function () {
    $navMain = file_get_contents(resource_path('js/components/NavMain.vue'));
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

    expect($navMain)->toContain('@mouseenter="revealHandleLater(item)"')
        ->and($navMain)->toContain('}, 2000);')
        ->and($navMain)->toContain('v-for="dot in 6"')
        ->and($navMain)->toContain(':draggable="!props.reordering"')
        ->and($sidebar)->toContain(':reorderable="true"')
        ->and($sidebar)->toContain('@reorder="persistMenuOrder"')
        ->and($sidebar)->toContain("method: 'PATCH'")
        ->and($sidebar)->toContain('menu.order = response.order');
});

test('menu settings page exposes explicit sidebar order controls including settings', function () {
    $menuPage = file_get_contents(resource_path('js/pages/settings/Menu.vue'));
    $english = require lang_path('en/ui/menu.php');
    $russian = require lang_path('ru/ui/menu.php');

    expect($menuPage)->toContain('const sidebarBuiltInKeys = [')
        ->and($menuPage)->toContain("key: 'settings'")
        ->and($menuPage)->toContain('const orderedSidebarItems = computed<SidebarOrderItem[]>(() => {')
        ->and($menuPage)->toContain('updateMenuOrder.url()')
        ->and($menuPage)->toContain('@click="moveSidebarItem(item.key, \'up\')"')
        ->and($menuPage)->toContain('@click="moveSidebarItem(item.key, \'down\')"')
        ->and($menuPage)->toContain('t.menu.sidebar_order')
        ->and($english['sidebar_order'])->toBe('Sidebar order')
        ->and($english['move_up'])->toBe('Move up')
        ->and($english['move_down'])->toBe('Move down')
        ->and($russian['sidebar_order'])->toBe('Порядок бокового меню')
        ->and($russian['move_up'])->toBe('Переместить вверх')
        ->and($russian['move_down'])->toBe('Переместить вниз');
});

test('collapsed sidebar opens the parent settings item using its default href', function () {
    $navMain = file_get_contents(resource_path('js/components/NavMain.vue'));
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

    expect($navMain)->toContain('const useParentHrefWhenCollapsed = computed(() => {')
        ->and($navMain)->toContain("return state.value === 'collapsed' && !isMobile.value;")
        ->and($navMain)->toContain('v-if="item.items?.length && useParentHrefWhenCollapsed"')
        ->and($navMain)->toContain('<Link v-else :href="item.href">')
        ->and($sidebar)->toContain('href: settingsItems[0]?.href ?? editMenu()');
});

test('tasks and projects sidebar item renders two submenu links', function () {
    $navMain = file_get_contents(resource_path('js/components/NavMain.vue'));
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

    expect($navMain)->toContain('class="min-w-0 flex-1 truncate">{{')
        ->and($sidebar)->toContain("isMenuItemVisible('projects')")
        ->and($sidebar)->toContain('title: t.value.projects.tasks')
        ->and($sidebar)->toContain('href: tasksIndex()')
        ->and($sidebar)->toContain('title: t.value.projects.projects_label')
        ->and($sidebar)->toContain('href: projectsIndex()');
});
