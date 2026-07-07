<?php

use App\Models\User;
use App\Models\UserGroup;
use Inertia\Testing\AssertableInertia as Assert;

test('only the configured super admin can open user management settings', function () {
    config(['admin.super_admin_email' => 'admin@example.com']);

    $user = User::factory()->create([
        'email' => 'user@example.com',
    ]);

    $this->actingAs($user)
        ->get(route('settings.users.index'))
        ->assertForbidden();

    $admin = User::factory()->create([
        'email' => 'admin@example.com',
    ]);

    $this->actingAs($admin)
        ->get(route('settings.users.index'))
        ->assertSuccessful();
});

test('super admin can view every user and their group state', function () {
    config(['admin.super_admin_email' => 'admin@example.com']);

    $admin = User::factory()->create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
    ]);

    $group = UserGroup::factory()->create([
        'name' => 'Managers',
    ]);

    $administrators = UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);

    $assignedUser = User::factory()->create([
        'name' => 'Assigned User',
        'last_name' => 'Manager',
        'email' => 'assigned@example.com',
        'phone' => '+77011234567',
        'user_group_id' => $group->id,
    ]);

    $simpleUser = User::factory()->create([
        'name' => 'Simple User',
        'email' => 'simple@example.com',
        'user_group_id' => null,
    ]);

    $administrator = User::factory()->create([
        'name' => 'Department Admin',
        'email' => 'department-admin@example.com',
        'user_group_id' => $administrators->id,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('settings.users.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Users')
            ->has('users.data', 4)
            ->where('users.meta.per_page', 50)
            ->where('can.manage_users', true)
            ->where('visibleUserTableColumns', [])
            ->has('groups')
        );

    $users = collect($response->inertiaProps('users.data'));
    $groups = collect($response->inertiaProps('groups'));
    $managerOptions = collect($response->inertiaProps('managerOptions'));

    expect($users->firstWhere('email', $admin->email)['is_super_admin'])->toBeTrue()
        ->and($users->firstWhere('email', $admin->email)['can_be_impersonated'])->toBeFalse()
        ->and($users->firstWhere('email', $assignedUser->email)['last_name'])->toBe('Manager')
        ->and($users->firstWhere('email', $assignedUser->email)['phone'])->toBe('+77011234567')
        ->and($users->firstWhere('email', $assignedUser->email)['can_be_impersonated'])->toBeTrue()
        ->and($users->firstWhere('email', $assignedUser->email))->toHaveKeys(['avatar', 'avatar_scale', 'created_at'])
        ->and($users->firstWhere('email', $administrator->email)['group']['name'])->toBe(UserGroup::ADMINISTRATORS_NAME)
        ->and($users->firstWhere('email', $administrator->email)['can_be_impersonated'])->toBeTrue()
        ->and($users->firstWhere('email', $assignedUser->email)['group']['name'])->toBe('Managers')
        ->and($users->firstWhere('email', $simpleUser->email)['group'])->toBeNull()
        ->and($users->firstWhere('email', $simpleUser->email)['can_be_impersonated'])->toBeTrue()
        ->and($managerOptions->firstWhere('email', $assignedUser->email))->toHaveKeys(['avatar', 'avatar_scale'])
        ->and($managerOptions->firstWhere('email', $assignedUser->email)['avatar_scale'])->toBeNumeric()
        ->and($groups->pluck('name')->all())->toContain('Administrators', 'Managers');
});

test('super admin can create user groups', function () {
    config(['admin.super_admin_email' => 'admin@example.com']);

    $admin = User::factory()->create([
        'email' => 'admin@example.com',
    ]);

    $this->actingAs($admin)
        ->post(route('settings.groups.store'), [
            'name' => 'Support',
            'description' => 'Support team members',
        ])
        ->assertRedirect();

    $this->assertModelExists(UserGroup::query()->where('name', 'Support')->first());
});

test('super admin can paginate user groups with supported page sizes', function () {
    config(['admin.super_admin_email' => 'admin@example.com']);

    $admin = User::factory()->create([
        'email' => 'admin@example.com',
    ]);

    UserGroup::factory()->count(60)->create();

    $response = $this->actingAs($admin)
        ->get(route('settings.groups.index'))
        ->assertSuccessful();

    expect($response->inertiaProps('groups.meta.per_page'))->toBe(50)
        ->and(count($response->inertiaProps('groups.data')))->toBe(50)
        ->and($response->inertiaProps('perPageOptions'))->toBe([50, 100, 500]);
});

test('super admin can assign and remove a user group', function () {
    config(['admin.super_admin_email' => 'admin@example.com']);

    $admin = User::factory()->create([
        'email' => 'admin@example.com',
    ]);

    $user = User::factory()->create([
        'user_group_id' => null,
    ]);

    $group = UserGroup::factory()->create();

    $this->actingAs($admin)
        ->patch(route('settings.users.group.update', $user), [
            'user_group_id' => $group->id,
        ])
        ->assertRedirect();

    expect($user->refresh()->user_group_id)->toBe($group->id);

    $this->actingAs($admin)
        ->patch(route('settings.users.group.update', $user), [
            'user_group_id' => null,
        ])
        ->assertRedirect();

    expect($user->refresh()->user_group_id)->toBeNull();
});

test('users page hides action buttons only for the super admin user row', function () {
    $usersPage = file_get_contents(resource_path('js/pages/settings/Users.vue'));

    expect($usersPage)
        ->toContain('const showUserActionsColumn = computed(() => {')
        ->toContain('const showUserActions = (user: UserRow): boolean => {')
        ->toContain('return !user.is_super_admin && showUserActionsColumn.value;')
        ->toContain('v-if="showUserActions(user)"')
        ->toContain('v-if="can.manage_accounts"')
        ->toContain('v-if="can.manage_activation"')
        ->toContain('v-if="can.impersonate_users"');
});

test('users page offers a dropdown to choose optional user table columns', function () {
    $usersPage = file_get_contents(resource_path('js/pages/settings/Users.vue'));

    expect($usersPage)
        ->toContain('DropdownMenuCheckboxItem')
        ->toContain('visibleUserTableColumns')
        ->toContain('setUserTableColumnVisibility(')
        ->toContain("isUserTableColumnVisible('position')")
        ->toContain("isUserTableColumnVisible('manager')")
        ->toContain("isUserTableColumnVisible('status')")
        ->toContain("isUserTableColumnVisible('email_verified')")
        ->toContain("isUserTableColumnVisible('group')");
});

test('users page stores the selected optional columns for the current viewer', function () {
    config(['admin.super_admin_email' => 'admin@example.com']);

    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'visible_user_table_columns' => ['status'],
    ]);

    $this->actingAs($admin)
        ->patch(route('settings.users.table-columns.update'), [
            'visible_columns' => ['position', 'group', 'status'],
        ])
        ->assertRedirect();

    expect($admin->fresh()->visible_user_table_columns)
        ->toBe(['position', 'status', 'group']);

    $this->actingAs($admin)
        ->get(route('settings.users.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Users')
            ->where('visibleUserTableColumns', ['position', 'status', 'group'])
        );
});

test('super admin can search users by name last name and email', function () {
    config(['admin.super_admin_email' => 'admin@example.com']);

    $administrators = UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);

    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'user_group_id' => $administrators->id,
    ]);

    User::factory()->create([
        'name' => 'Alice',
        'last_name' => 'Johnson',
        'email' => 'alice@example.com',
    ]);

    User::factory()->create([
        'name' => 'Bob',
        'last_name' => 'Smith',
        'email' => 'bob@example.com',
    ]);

    $byName = $this->actingAs($admin)->get(route('settings.users.index', [
        'search' => 'Alice',
    ]));
    $byLastName = $this->actingAs($admin)->get(route('settings.users.index', [
        'search' => 'Smith',
    ]));
    $byEmail = $this->actingAs($admin)->get(route('settings.users.index', [
        'search' => 'alice@example.com',
    ]));

    expect(collect($byName->inertiaProps('users.data'))->pluck('email')->all())
        ->toBe(['alice@example.com'])
        ->and($byName->inertiaProps('filters.search'))->toBe('Alice')
        ->and(collect($byLastName->inertiaProps('users.data'))->pluck('email')->all())
        ->toBe(['bob@example.com'])
        ->and($byLastName->inertiaProps('filters.search'))->toBe('Smith')
        ->and(collect($byEmail->inertiaProps('users.data'))->pluck('email')->all())
        ->toBe(['alice@example.com'])
        ->and($byEmail->inertiaProps('filters.search'))->toBe('alice@example.com');
});

test('super admin can filter users by status group and registration date', function () {
    config(['admin.super_admin_email' => 'admin@example.com']);

    $administrators = UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);

    $managers = UserGroup::factory()->create([
        'name' => 'Managers',
    ]);

    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'user_group_id' => $administrators->id,
    ]);

    $inactiveManager = User::factory()->create([
        'name' => 'Inactive Manager',
        'email' => 'inactive.manager@example.com',
        'user_group_id' => $managers->id,
        'is_active' => false,
        'deactivated_at' => now(),
    ]);

    $simpleUser = User::factory()->create([
        'name' => 'Simple User',
        'email' => 'simple@example.com',
        'user_group_id' => null,
    ]);

    $activeManager = User::factory()->create([
        'name' => 'Active Manager',
        'email' => 'active.manager@example.com',
        'user_group_id' => $managers->id,
    ]);

    $inactiveManager->forceFill(['created_at' => '2026-02-12 10:00:00'])->saveQuietly();
    $simpleUser->forceFill(['created_at' => '2026-03-12 10:00:00'])->saveQuietly();
    $activeManager->forceFill(['created_at' => '2026-04-12 10:00:00'])->saveQuietly();

    $filteredByStatusAndGroup = $this->actingAs($admin)->get(route('settings.users.index', [
        'status' => 'inactive',
        'group' => (string) $managers->id,
    ]));

    $filteredByDates = $this->actingAs($admin)->get(route('settings.users.index', [
        'registered_from' => '2026-03-01',
        'registered_to' => '2026-03-31',
        'group' => 'none',
    ]));

    expect(collect($filteredByStatusAndGroup->inertiaProps('users.data'))->pluck('email')->all())
        ->toBe(['inactive.manager@example.com'])
        ->and($filteredByStatusAndGroup->inertiaProps('filters.status'))->toBe('inactive')
        ->and($filteredByStatusAndGroup->inertiaProps('filters.group'))->toBe((string) $managers->id)
        ->and(collect($filteredByDates->inertiaProps('users.data'))->pluck('email')->all())
        ->toBe(['simple@example.com'])
        ->and($filteredByDates->inertiaProps('filters.registered_from'))->toBe('2026-03-01')
        ->and($filteredByDates->inertiaProps('filters.registered_to'))->toBe('2026-03-31')
        ->and($filteredByDates->inertiaProps('filters.group'))->toBe('none');
});

test('user management uses 50 items by default and allows selecting supported page sizes', function () {
    config(['admin.super_admin_email' => 'admin@example.com']);

    $admin = User::factory()->create([
        'email' => 'admin@example.com',
    ]);

    User::factory()->count(120)->create();

    $defaultPage = $this->actingAs($admin)
        ->get(route('settings.users.index'))
        ->assertSuccessful();

    $hundredPerPage = $this->actingAs($admin)
        ->get(route('settings.users.index', ['per_page' => 100]))
        ->assertSuccessful();

    expect($defaultPage->inertiaProps('users.meta.per_page'))->toBe(50)
        ->and(count($defaultPage->inertiaProps('users.data')))->toBe(50)
        ->and($hundredPerPage->inertiaProps('users.meta.per_page'))->toBe(100)
        ->and(count($hundredPerPage->inertiaProps('users.data')))->toBe(100)
        ->and($hundredPerPage->inertiaProps('perPageOptions'))->toBe([50, 100, 500]);
});
