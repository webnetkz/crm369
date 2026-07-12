<?php

use App\Models\User;
use App\Models\UserLoginActivity;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')
            ->where('csrfToken', fn (?string $token) => is_string($token) && $token !== '')
        );
});

test('mobile app login screen uses built vite assets instead of hot server assets', function () {
    $response = $this->withHeaders([
        'User-Agent' => 'Mozilla/5.0 CRM369MobileApp',
    ])->get(route('login'));

    $response->assertOk()
        ->assertDontSee('@vite/client', false)
        ->assertSee('/build/assets/', false);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();
    $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    $response = $this
        ->withHeader('User-Agent', $userAgent)
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $activity = UserLoginActivity::query()
        ->whereBelongsTo($user)
        ->latest('logged_in_at')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity?->ip_address)->toBe('127.0.0.1')
        ->and($activity?->user_agent)->toBe($userAgent)
        ->and($activity?->browser)->toBe('Chrome')
        ->and($activity?->platform)->toBe('macOS')
        ->and($activity?->device_type)->toBe('desktop')
        ->and($activity?->is_new_device)->toBeTrue()
        ->and($activity?->is_new_ip)->toBeTrue();
});

test('mobile app login requests are redirected to dashboard instead of receiving json payload', function () {
    $user = User::factory()->create();

    $response = $this->withHeaders([
        'User-Agent' => 'Mozilla/5.0 CRM369MobileApp',
        'Accept' => 'application/json',
    ])->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('mobile app login ignores intended mobile notification feed and opens dashboard', function () {
    $user = User::factory()->create();

    $response = $this
        ->withHeaders([
            'User-Agent' => 'Mozilla/5.0 CRM369MobileApp',
            'Accept' => 'application/json',
        ])
        ->withSession([
            'url.intended' => route('mobile.notifications.feed', absolute: false),
        ])
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('repeat logins from the same device and ip are not flagged as new', function () {
    $user = User::factory()->create();
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    $this->withHeader('User-Agent', $userAgent)->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->post(route('logout'));

    $this->withHeader('User-Agent', $userAgent)->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $activities = UserLoginActivity::query()
        ->whereBelongsTo($user)
        ->orderBy('logged_in_at')
        ->get();

    expect($activities)->toHaveCount(2)
        ->and($activities[0]->is_new_device)->toBeTrue()
        ->and($activities[0]->is_new_ip)->toBeTrue()
        ->and($activities[1]->is_new_device)->toBeFalse()
        ->and($activities[1]->is_new_ip)->toBeFalse();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('mobile app two factor login ignores intended mobile notification feed and opens dashboard', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $this->withHeaders([
        'User-Agent' => 'Mozilla/5.0 CRM369MobileApp',
    ])->withSession([
        'url.intended' => route('mobile.notifications.feed', absolute: false),
    ])->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('two-factor.login'));

    $response = $this->withHeaders([
        'User-Agent' => 'Mozilla/5.0 CRM369MobileApp',
    ])->post(route('two-factor.login.store'), [
        'recovery_code' => 'recovery-code-1',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
