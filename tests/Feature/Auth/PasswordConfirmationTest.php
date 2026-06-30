<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('confirm password screen can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('password.confirm'));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/ConfirmPassword'),
    );
});

test('confirm password screen includes a large back button to profile settings', function () {
    $page = file_get_contents(resource_path('js/pages/auth/ConfirmPassword.vue'));

    expect($page)->toContain('t.common.back')
        ->and($page)->toContain('editProfile()')
        ->and($page)->toContain('size="lg"');
});

test('password confirmation requires authentication', function () {
    $response = $this->get(route('password.confirm'));

    $response->assertRedirect(route('login'));
});
