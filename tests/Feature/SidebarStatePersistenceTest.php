<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('shared sidebar state defaults to open when the cookie is absent', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sidebarOpen', true)
        );
});

test('shared sidebar state respects the sidebar cookie value', function (string $cookieValue, bool $expectedOpenState) {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->withUnencryptedCookie('sidebar_state', $cookieValue)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('sidebarOpen', $expectedOpenState)
        );
})->with([
    ['true', true],
    ['false', false],
]);

test('sidebar provider uses a server supplied default instead of reading document cookies during hydration', function () {
    $sidebarProvider = file_get_contents(resource_path('js/components/ui/sidebar/SidebarProvider.vue'));

    expect($sidebarProvider)->toContain('defaultOpen: true')
        ->and($sidebarProvider)->toContain('document.cookie = `${SIDEBAR_COOKIE_NAME}=${value}; path=/; max-age=${SIDEBAR_COOKIE_MAX_AGE}`')
        ->and($sidebarProvider)->not->toContain('defaultDocument?.cookie.includes')
        ->and($sidebarProvider)->not->toContain('document.cookie = `${SIDEBAR_COOKIE_NAME}=${open.value}; path=/; max-age=${SIDEBAR_COOKIE_MAX_AGE}`');
});
