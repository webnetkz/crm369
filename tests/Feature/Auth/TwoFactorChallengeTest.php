<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

test('two factor challenge redirects to login when not authenticated', function () {
    $response = $this->get(route('two-factor.login'));

    $response->assertRedirect(route('login'));
});

test('two factor challenge can be rendered', function () {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->get(route('two-factor.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/TwoFactorChallenge'),
        );
});

test('two factor challenge source focuses the active authentication field automatically', function () {
    $page = file_get_contents(resource_path('js/pages/auth/TwoFactorChallenge.vue'));

    expect($page)
        ->toContain('const focusChallengeField = (): void => {')
        ->toContain("'input[data-input-otp]'")
        ->toContain("'input[name=\"recovery_code\"]'")
        ->toContain('onMounted(() => {')
        ->toContain('watch(showRecoveryInput, () => {');
});
