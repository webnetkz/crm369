<?php

use App\Models\EquipmentItem;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Support\Facades\Hash;

test('inactive users cannot authenticate with a password', function () {
    $user = User::factory()->create([
        'is_active' => false,
        'deactivated_at' => now(),
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('inactive authenticated users are logged out on the next request', function () {
    $user = User::factory()->create([
        'is_active' => false,
        'deactivated_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('super admin can deactivate and restore a user', function () {
    config(['admin.super_admin_email' => 'admin@example.com']);

    $admin = User::factory()->create([
        'email' => 'admin@example.com',
    ]);

    $user = User::factory()->create();

    $this->actingAs($admin)
        ->patch(route('settings.users.activation.update', $user), [
            'is_active' => false,
        ])
        ->assertRedirect();

    expect($user->refresh()->is_active)->toBeFalse()
        ->and($user->deactivated_at)->not->toBeNull();

    $this->actingAs($admin)
        ->patch(route('settings.users.activation.update', $user), [
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($user->refresh()->is_active)->toBeTrue()
        ->and($user->deactivated_at)->toBeNull();
});

test('administrators group can view users and manage activation but cannot manage groups', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $administrators = UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);

    $adminUser = User::factory()->create([
        'user_group_id' => $administrators->id,
    ]);

    $targetUser = User::factory()->create();

    $this->actingAs($adminUser)
        ->get(route('settings.users.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Users')
            ->where('can.manage_users', false)
            ->where('can.manage_activation', true)
            ->where('can.manage_accounts', true)
            ->where('can.impersonate_users', false)
            ->has('users.data')
        );

    $this->actingAs($adminUser)
        ->get(route('settings.groups.index'))
        ->assertForbidden();

    $this->actingAs($adminUser)
        ->patch(route('settings.users.activation.update', $targetUser), [
            'is_active' => false,
        ])
        ->assertRedirect();

    expect($targetUser->refresh()->is_active)->toBeFalse();

    $this->actingAs($adminUser)
        ->patch(route('settings.users.activation.update', $targetUser), [
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($targetUser->refresh()->is_active)->toBeTrue();
});

test('regular users cannot manage activation', function () {
    $user = User::factory()->create();
    $targetUser = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('settings.users.activation.update', $targetUser), [
            'is_active' => false,
        ])
        ->assertForbidden();
});

test('administrators group can create a simple verified user manually', function () {
    $administrators = UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);

    $adminUser = User::factory()->create([
        'user_group_id' => $administrators->id,
    ]);

    $this->actingAs($adminUser)
        ->post(route('settings.users.store'), [
            'name' => 'Created User',
            'email' => 'created@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'email_verified' => true,
        ])
        ->assertRedirect();

    $createdUser = User::query()->where('email', 'created@example.com')->firstOrFail();

    expect($createdUser->name)->toBe('Created User')
        ->and($createdUser->user_group_id)->toBeNull()
        ->and($createdUser->email_verified_at)->not->toBeNull()
        ->and(Hash::check('password', $createdUser->password))->toBeTrue();
});

test('manual user creation form includes password generator support', function () {
    $usersPage = file_get_contents(resource_path('js/pages/settings/Users.vue'));
    $generator = file_get_contents(resource_path('js/composables/usePasswordGenerator.ts'));

    expect($usersPage)->toContain('applyGeneratedCreateUserPassword')
        ->and($usersPage)->toContain('useClipboard')
        ->and($usersPage)->toContain('canAutoSubmitCreateUser')
        ->and($usersPage)->toContain('submitCreateUser()')
        ->and($usersPage)->toContain('createUserDialogOpen')
        ->and($usersPage)->toContain('@click="openCreateUserDialog"')
        ->and($usersPage)->toContain('t.common.generate_password')
        ->and($usersPage)->toContain('v-model="createUserForm.password"')
        ->and($usersPage)->toContain('PasswordInput')
        ->and($generator)->toContain('symbolCharacters');

    expect(
        strpos($usersPage, 'v-model="createUserForm.password_confirmation"')
    )->toBeLessThan(
        strpos($usersPage, '@click="applyGeneratedCreateUserPassword"')
    );
});

test('users page shows user creation in a modal dialog', function () {
    $usersPage = file_get_contents(resource_path('js/pages/settings/Users.vue'));

    expect($usersPage)
        ->toContain('<Dialog')
        ->toContain(':open="createUserDialogOpen"')
        ->toContain('closeCreateUserDialog')
        ->toContain('t.admin.create_user_description')
        ->toContain('@submit.prevent="submitCreateUser"')
        ->not
        ->toContain('v-if="can.manage_accounts"\n            class="space-y-4 rounded-lg border border-border p-4"');
});

test('administrators group can reset user passwords manually', function () {
    $administrators = UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);

    $adminUser = User::factory()->create([
        'user_group_id' => $administrators->id,
    ]);

    $targetUser = User::factory()->create();

    $this->actingAs($adminUser)
        ->patch(route('settings.users.password.reset', $targetUser), [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertRedirect();

    expect(Hash::check('new-password', $targetUser->refresh()->password))->toBeTrue();
});

test('administrators group cannot reset a super admin password', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $administrators = UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);

    $adminUser = User::factory()->create([
        'user_group_id' => $administrators->id,
    ]);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
        'password' => 'original-password',
    ]);

    $this->actingAs($adminUser)
        ->patch(route('settings.users.password.reset', $superAdmin), [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertRedirect();

    expect(Hash::check('original-password', $superAdmin->refresh()->password))->toBeTrue();
});

test('administrators group can update a regular user profile', function () {
    $administrators = UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);

    $adminUser = User::factory()->create([
        'user_group_id' => $administrators->id,
    ]);

    $targetUser = User::factory()->create([
        'name' => 'Old Name',
        'last_name' => 'Old Last Name',
        'email' => 'old@example.com',
        'phone' => '+77010001122',
    ]);

    $this->actingAs($adminUser)
        ->patch(route('settings.users.profile.update', $targetUser), [
            'name' => 'Updated Name',
            'last_name' => 'Updated Last Name',
            'email' => 'updated@example.com',
            'phone' => '+7 777 123 45 67',
        ])
        ->assertRedirect();

    expect($targetUser->refresh()->name)->toBe('Updated Name')
        ->and($targetUser->last_name)->toBe('Updated Last Name')
        ->and($targetUser->email)->toBe('updated@example.com')
        ->and($targetUser->phone)->toBe('+77771234567')
        ->and($targetUser->email_verified_at)->toBeNull();
});

test('administrators group cannot update a super admin profile', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $administrators = UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);

    $adminUser = User::factory()->create([
        'user_group_id' => $administrators->id,
    ]);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
        'name' => 'Super Admin',
    ]);

    $this->actingAs($adminUser)
        ->patch(route('settings.users.profile.update', $superAdmin), [
            'name' => 'Changed Name',
            'email' => 'super@example.com',
            'phone' => '+7',
        ])
        ->assertForbidden();

    expect($superAdmin->refresh()->name)->toBe('Super Admin');
});

test('regular users cannot create users or reset passwords', function () {
    $user = User::factory()->create();
    $targetUser = User::factory()->create();

    $this->actingAs($user)
        ->post(route('settings.users.store'), [
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'email_verified' => true,
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('settings.users.password.reset', $targetUser), [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('settings.users.profile.update', $targetUser), [
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'phone' => '+7',
        ])
        ->assertForbidden();
});

test('users page shows password reset in a modal dialog', function () {
    $usersPage = file_get_contents(resource_path('js/pages/settings/Users.vue'));

    expect($usersPage)
        ->toContain('<Dialog')
        ->toContain('<DialogContent')
        ->toContain('<DialogFooter')
        ->toContain('selectedPasswordUser !== null')
        ->toContain('@submit.prevent="submitPasswordReset"')
        ->not
        ->toContain('v-if="selectedPasswordUser"\n            class="space-y-4 rounded-lg border border-border p-4"');
});

test('users page shows profile details in a right sheet sidebar', function () {
    $usersPage = file_get_contents(resource_path('js/pages/settings/Users.vue'));
    $profileSheet = file_get_contents(resource_path('js/components/UserProfileSheet.vue'));

    expect($usersPage)
        ->toContain('<UserProfileSheet')
        ->toContain('selectedProfileUser !== null')
        ->toContain('@click="openProfile(user)"')
        ->and($profileSheet)->toContain('t.admin.profile_description')
        ->and($profileSheet)->toContain('formatDateTime(')
        ->and($profileSheet)->toContain('t.admin.simple_user')
        ->and($profileSheet)->toContain('user?.issued_equipment?.length')
        ->and($profileSheet)->toContain('t.profile.issued_equipment')
        ->and($profileSheet)->toContain('equipmentItem.qr_code_svg_data_uri')
        ->and($profileSheet)->toContain('profile_autosave_saving');
});

test('managed user profile payload includes issued equipment', function () {
    $administrators = UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);

    $adminUser = User::factory()->create([
        'user_group_id' => $administrators->id,
    ]);

    $targetUser = User::factory()->create([
        'name' => 'Issued User',
    ]);

    $responsibleUser = User::factory()->create([
        'name' => 'Responsible',
        'last_name' => 'Manager',
    ]);

    EquipmentItem::factory()->issued()->create([
        'name' => 'User Sheet Scanner',
        'qr_code' => 'EQ-USER-SHEET-01',
        'issued_to_user_id' => $targetUser->id,
        'responsible_user_id' => $responsibleUser->id,
        'created_by_user_id' => $adminUser->id,
        'updated_by_user_id' => $adminUser->id,
    ]);

    $this->actingAs($adminUser)
        ->getJson(route('settings.users.show', $targetUser))
        ->assertSuccessful()
        ->assertJsonPath('data.issued_equipment.0.name', 'User Sheet Scanner')
        ->assertJsonPath('data.issued_equipment.0.qr_code', 'EQ-USER-SHEET-01')
        ->assertJsonPath('data.issued_equipment.0.qr_code_svg_data_uri', fn (string $value): bool => str_starts_with($value, 'data:image/svg+xml;utf8,'))
        ->assertJsonPath('data.issued_equipment.0.responsible_user.name', 'Responsible');
});

test('shared auth user payload includes issued equipment for sidebar profile sheet', function () {
    $user = User::factory()->create([
        'name' => 'Sidebar User',
    ]);

    $responsibleUser = User::factory()->create([
        'name' => 'Sidebar Responsible',
        'last_name' => 'Manager',
    ]);

    EquipmentItem::factory()->issued()->create([
        'name' => 'Sidebar QR Scanner',
        'qr_code' => 'EQ-SIDEBAR-01',
        'issued_to_user_id' => $user->id,
        'responsible_user_id' => $responsibleUser->id,
        'created_by_user_id' => $responsibleUser->id,
        'updated_by_user_id' => $responsibleUser->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('auth.user.issued_equipment.0.name', 'Sidebar QR Scanner')
            ->where('auth.user.issued_equipment.0.qr_code', 'EQ-SIDEBAR-01')
            ->where('auth.user.issued_equipment.0.qr_code_svg_data_uri', fn (string $value): bool => str_starts_with($value, 'data:image/svg+xml;utf8,'))
            ->where('auth.user.issued_equipment.0.responsible_user.name', 'Sidebar Responsible')
        );
});

test('users page supports inline profile editing with autosave', function () {
    $usersPage = file_get_contents(resource_path('js/pages/settings/Users.vue'));
    $profileSheet = file_get_contents(resource_path('js/components/UserProfileSheet.vue'));

    expect($usersPage)
        ->toContain('managedProfileForm')
        ->toContain('scheduleManagedProfileSave')
        ->toContain('submitManagedProfileUpdate')
        ->toContain('updateUserProfile.url')
        ->and($profileSheet)->toContain('profile_autosave_saving')
        ->and($profileSheet)->toContain('profile_autosave_saved');
});

test('sidebar user button opens a left bottom sheet instead of a dropdown', function () {
    $navUser = file_get_contents(resource_path('js/components/NavUser.vue'));

    expect($navUser)
        ->toContain('<Sheet')
        ->toContain('<SheetTrigger as-child>')
        ->toContain('side="left"')
        ->toContain('bottom-4 left-4')
        ->toContain('userGroupLabel')
        ->toContain('formatDateTime(user.created_at ?? null)')
        ->toContain('user.issued_equipment?.length')
        ->toContain('equipmentItem.qr_code_svg_data_uri')
        ->not
        ->toContain('<DropdownMenu');
});

test('sidebar profile sheet closes before navigating to settings', function () {
    $navUser = file_get_contents(resource_path('js/components/NavUser.vue'));

    expect($navUser)
        ->toContain('const profileSheetOpen = ref(false);')
        ->toContain(':open="profileSheetOpen"')
        ->toContain('@update:open="profileSheetOpen = $event"')
        ->toContain('const closeProfileSheet = (): void => {')
        ->toContain('@click="closeProfileSheet"');
});
