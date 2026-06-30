<?php

use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register and remain pending administrator approval', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    $this->assertGuest();
    $response
        ->assertRedirect(route('login', absolute: false))
        ->assertSessionHas('status', __('ui.auth.registration_pending_approval'));

    expect($user->name)->toBe('Test User')
        ->and($user->is_active)->toBeFalse()
        ->and($user->deactivated_at)->toBeNull();
});

test('registration screen includes password generator support', function () {
    $registerPage = file_get_contents(resource_path('js/pages/auth/Register.vue'));
    $passwordInput = file_get_contents(resource_path('js/components/PasswordInput.vue'));
    $generator = file_get_contents(resource_path('js/composables/usePasswordGenerator.ts'));

    expect($registerPage)->toContain('applyGeneratedPassword')
        ->and($registerPage)->toContain('useClipboard')
        ->and($registerPage)->toContain('canAutoSubmitRegistration')
        ->and($registerPage)->toContain('submit();')
        ->and($registerPage)->toContain('t.common.generate_password')
        ->and($registerPage)->toContain('v-model="form.password"')
        ->and($passwordInput)->toContain('modelValue')
        ->and($generator)->toContain('generatePassword');
});
