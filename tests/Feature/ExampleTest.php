<?php

use Inertia\Testing\AssertableInertia as Assert;

test('home page renders the login screen', function () {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')
            ->has('canResetPassword')
            ->has('status'),
        );
});
