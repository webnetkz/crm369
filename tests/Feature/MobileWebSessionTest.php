<?php

use App\Models\MobileAccessToken;
use App\Models\User;

function mobileWebSessionToken(User $user): string
{
    return MobileAccessToken::issueToUser($user, 'android-web-session-device')['plain_text_token'];
}

function mobileWebSessionLinkToken(string $url): string
{
    return basename((string) parse_url($url, PHP_URL_PATH));
}

test('a mobile session can open a web page with the same user signed in', function () {
    $user = User::factory()->create();

    $response = $this->withToken(mobileWebSessionToken($user))
        ->postJson(route('mobile.v1.web-session.store'), ['path' => '/settings/users?tab=active'])
        ->assertCreated()
        ->assertJsonPath('data.path', '/settings/users?tab=active')
        ->assertJsonPath('data.expires_in', 60);

    $url = $response->json('data.url');

    expect($url)->toStartWith(route('mobile.web-session.consume', ['token' => mobileWebSessionLinkToken($url)]));

    $this->app['auth']->forgetGuards();

    $this->get($url)->assertRedirect('/settings/users?tab=active');

    $this->assertAuthenticatedAs($user, 'web');
});

test('web session links are single use', function () {
    $user = User::factory()->create();

    $url = $this->withToken(mobileWebSessionToken($user))
        ->postJson(route('mobile.v1.web-session.store'), ['path' => '/dashboard'])
        ->json('data.url');

    $this->app['auth']->forgetGuards();
    $this->get($url)->assertRedirect('/dashboard');

    auth('web')->logout();
    $this->app['auth']->forgetGuards();

    $this->get($url)->assertRedirect(route('home'));
    $this->assertGuest('web');
});

test('web session links only redirect to same origin paths', function (string $requestedPath) {
    $user = User::factory()->create();

    $this->withToken(mobileWebSessionToken($user))
        ->postJson(route('mobile.v1.web-session.store'), ['path' => $requestedPath])
        ->assertCreated()
        ->assertJsonPath('data.path', '/dashboard');
})->with([
    'absolute url' => 'https://evil.example/phish',
    'protocol relative url' => '//evil.example/phish',
    'backslash url' => '/\\evil.example',
    'relative path' => 'settings/users',
]);

test('web session links stop working when the mobile session is revoked', function () {
    $user = User::factory()->create();
    $token = mobileWebSessionToken($user);

    $url = $this->withToken($token)
        ->postJson(route('mobile.v1.web-session.store'), ['path' => '/dashboard'])
        ->json('data.url');

    MobileAccessToken::query()->whereBelongsTo($user)->delete();
    $this->app['auth']->forgetGuards();

    $this->get($url)->assertRedirect(route('home'));
    $this->assertGuest('web');
});

test('web session links are not issued for deactivated users or guests', function () {
    $this->postJson(route('mobile.v1.web-session.store'), ['path' => '/dashboard'])
        ->assertUnauthorized();

    $user = User::factory()->create();
    $token = mobileWebSessionToken($user);
    $user->forceFill(['is_active' => false])->save();

    $this->withToken($token)
        ->postJson(route('mobile.v1.web-session.store'), ['path' => '/dashboard'])
        ->assertUnauthorized();
});

test('malformed web session tokens are rejected', function () {
    $this->get('/mobile/web-session/'.str_repeat('a', 64))->assertRedirect(route('home'));
    $this->get('/mobile/web-session/short-token')->assertNotFound();
    $this->assertGuest('web');
});
