<?php

use App\Models\EquipmentItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('profile page is displayed', function () {
    $user = User::factory()->create([
        'position' => 'Sales Manager',
        'middle_name' => 'Serikovna',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.position', 'Sales Manager')
            ->where('auth.user.middle_name', 'Serikovna')
        );
});

test('profile page shows issued equipment assigned to the current user', function () {
    $user = User::factory()->create();
    $responsibleUser = User::factory()->create([
        'name' => 'Responsible',
        'last_name' => 'Manager',
    ]);

    EquipmentItem::factory()->issued()->create([
        'name' => 'Profile Laptop',
        'qr_code' => 'EQ-PROFILE-01',
        'issued_to_user_id' => $user->id,
        'responsible_user_id' => $responsibleUser->id,
        'created_by_user_id' => $responsibleUser->id,
        'updated_by_user_id' => $responsibleUser->id,
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('issuedEquipment.0.name', 'Profile Laptop')
            ->where('issuedEquipment.0.qr_code', 'EQ-PROFILE-01')
            ->where('issuedEquipment.0.qr_code_svg_data_uri', fn (string $value): bool => str_starts_with($value, 'data:image/svg+xml;utf8,'))
            ->where('issuedEquipment.0.responsible_user.name', 'Responsible')
        );
});

test('profile page exposes the selected avatar url', function () {
    Storage::fake('public');

    $avatarPath = 'avatars/42/current-avatar.jpg';

    Storage::disk('public')->put($avatarPath, 'avatar-image');

    $user = User::factory()->create([
        'avatar_path' => $avatarPath,
        'avatar_scale' => 1.15,
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.avatar', Storage::disk('public')->url($avatarPath))
            ->where('auth.user.avatar_scale', 1.15)
        );
});

test('profile page does not expose account deletion UI', function () {
    $profilePage = file_get_contents(resource_path('js/pages/settings/Profile.vue'));

    expect($profilePage)->not->toContain('DeleteUser');
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'last_name' => 'Tester',
            'middle_name' => 'Testovna',
            'email' => 'test@example.com',
            'phone' => '+7 777 123 45 67',
            'position' => 'Head of Sales',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->last_name)->toBe('Tester');
    expect($user->middle_name)->toBe('Testovna');
    expect($user->email)->toBe('test@example.com');
    expect($user->phone)->toBe('+77771234567');
    expect($user->position)->toBe('Head of Sales');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
            'phone' => '+7',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull()
        ->and($user->phone)->toBeNull();
});

test('profile avatar can be uploaded and zoomed out', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('profile.update'), [
            '_method' => 'PATCH',
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '+7 701 000 11 22',
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
            'avatar_scale' => 0.65,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    Storage::disk('public')->assertExists($user->avatar_path);

    expect($user->avatar_path)->toStartWith('avatars/'.$user->id.'/')
        ->and($user->avatar)->not->toBeNull()
        ->and($user->phone)->toBe('+77010001122')
        ->and($user->avatar_scale)->toBe(0.65);
});

test('profile page exposes last name, middle name, phone, and position fields', function () {
    $profilePage = file_get_contents(resource_path('js/pages/settings/Profile.vue'));

    expect($profilePage)
        ->toContain('profileForm.last_name')
        ->toContain('profileForm.middle_name')
        ->toContain('profileForm.phone')
        ->toContain('profileForm.position')
        ->toContain('persistedAvatarUrl')
        ->toContain('props.issuedEquipment.length > 0')
        ->toContain('t.profile.issued_equipment')
        ->toContain('equipmentItem.qr_code_svg_data_uri')
        ->toContain('autocomplete="family-name"')
        ->toContain('autocomplete="additional-name"')
        ->toContain('autocomplete="tel"')
        ->toContain('autocomplete="organization-title"');
});

test('profile deletion route is not exposed', function () {
    expect(Route::has('profile.destroy'))->toBeFalse();
});
