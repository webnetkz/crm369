<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
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
            'email' => 'test@example.com',
            'phone' => '+7 777 123 45 67',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->last_name)->toBe('Tester');
    expect($user->email)->toBe('test@example.com');
    expect($user->phone)->toBe('+77771234567');
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

test('profile page exposes last name and phone fields', function () {
    $profilePage = file_get_contents(resource_path('js/pages/settings/Profile.vue'));

    expect($profilePage)
        ->toContain('profileForm.last_name')
        ->toContain('profileForm.phone')
        ->toContain('autocomplete="family-name"')
        ->toContain('autocomplete="tel"');
});

test('profile deletion route is not exposed', function () {
    expect(Route::has('profile.destroy'))->toBeFalse();
});
