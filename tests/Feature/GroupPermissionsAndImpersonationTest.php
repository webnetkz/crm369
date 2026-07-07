<?php

use App\Models\User;
use App\Models\UserGroup;
use Inertia\Testing\AssertableInertia as Assert;

test('super admin can view and update group rights', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $group = UserGroup::factory()->create([
        'name' => 'Support',
        'permissions' => [],
    ]);

    $this->actingAs($superAdmin)
        ->get(route('settings.rights.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Rights')
            ->has('groups.data')
            ->where('groups.meta.per_page', 50)
            ->has('availablePermissions')
            ->has('permissionGroups')
        );

    $this->actingAs($superAdmin)
        ->patch(route('settings.rights.update', $group), [
            'permissions' => [
                UserGroup::PERMISSION_VIEW_USERS,
                UserGroup::PERMISSION_IMPERSONATE_USERS,
            ],
        ])
        ->assertRedirect();

    expect($group->refresh()->resolvedPermissions())
        ->toContain(UserGroup::PERMISSION_VIEW_USERS, UserGroup::PERMISSION_IMPERSONATE_USERS);
});

test('super admin can explicitly configure module access for a group', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $group = UserGroup::factory()->create([
        'name' => 'Module Editors',
        'permissions' => [],
    ]);

    $member = User::factory()->create([
        'user_group_id' => $group->id,
    ]);

    $this->actingAs($superAdmin)
        ->patch(route('settings.rights.update', $group), [
            'permissions' => [
                UserGroup::PERMISSION_ACCESS_COMPANY_STRUCTURE,
            ],
            'configured_modules' => [
                'company-structure',
                'news',
            ],
        ])
        ->assertRedirect();

    expect($group->refresh()->resolvedPermissions())
        ->toContain(UserGroup::PERMISSION_ACCESS_COMPANY_STRUCTURE)
        ->and($group->configuredPermissionModules())
        ->toBe(['company-structure', 'news']);

    $this->actingAs($member)
        ->get(route('company-structure.index'))
        ->assertSuccessful();

    $this->actingAs($member)
        ->get(route('news.index'))
        ->assertForbidden();
});

test('non super admins cannot manage group rights', function () {
    $user = User::factory()->create();
    $group = UserGroup::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.rights.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('settings.rights.update', $group), [
            'permissions' => [UserGroup::PERMISSION_VIEW_USERS],
        ])
        ->assertForbidden();
});

test('user group with view users and impersonation rights can access users page and impersonate a regular user', function () {
    $group = UserGroup::factory()->create([
        'permissions' => [
            UserGroup::PERMISSION_VIEW_USERS,
            UserGroup::PERMISSION_IMPERSONATE_USERS,
        ],
    ]);

    $manager = User::factory()->create([
        'user_group_id' => $group->id,
    ]);

    $targetUser = User::factory()->create();

    $this->actingAs($manager)
        ->get(route('settings.users.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Users')
            ->where('can.manage_users', false)
            ->where('can.manage_activation', false)
            ->where('can.manage_accounts', false)
            ->where('can.impersonate_users', true)
        );

    $this->actingAs($manager)
        ->post(route('settings.users.impersonation.store', $targetUser))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($targetUser);
    expect(session('impersonator_id'))->toBe($manager->id);
});

test('impersonation cannot target super admins or administrators', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $group = UserGroup::factory()->create([
        'permissions' => [
            UserGroup::PERMISSION_VIEW_USERS,
            UserGroup::PERMISSION_IMPERSONATE_USERS,
        ],
    ]);

    $manager = User::factory()->create([
        'user_group_id' => $group->id,
    ]);

    $administrators = UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $administrator = User::factory()->create([
        'user_group_id' => $administrators->id,
    ]);

    $this->actingAs($manager)
        ->post(route('settings.users.impersonation.store', $superAdmin))
        ->assertRedirect();

    $this->assertAuthenticatedAs($manager);
    expect(session('impersonator_id'))->toBeNull();

    $this->actingAs($manager)
        ->post(route('settings.users.impersonation.store', $administrator))
        ->assertRedirect();

    $this->assertAuthenticatedAs($manager);
    expect(session('impersonator_id'))->toBeNull();
});

test('super admin can impersonate an administrator', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $administrators = UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $administrator = User::factory()->create([
        'name' => 'Portal Admin',
        'user_group_id' => $administrators->id,
    ]);

    $this->actingAs($superAdmin)
        ->post(route('settings.users.impersonation.store', $administrator))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($administrator);
    expect(session('impersonator_id'))->toBe($superAdmin->id);
});

test('impersonated users can return to the original account', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $targetUser = User::factory()->create();

    $this->actingAs($superAdmin)
        ->post(route('settings.users.impersonation.store', $targetUser))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($targetUser);
    expect(session('impersonator_id'))->toBe($superAdmin->id);

    $this->delete(route('settings.impersonation.destroy'))
        ->assertRedirect(route('settings.users.index'));

    $this->assertAuthenticatedAs($superAdmin);
    expect(session('impersonator_id'))->toBeNull();
});
