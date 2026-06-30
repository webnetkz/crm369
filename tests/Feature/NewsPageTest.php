<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users can open the news page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('news.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('news/Index')
        );
});

test('news is available in the sidebar and menu settings built-in items', function () {
    $user = User::factory()->create();

    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));
    $response = $this->actingAs($user)
        ->get(route('settings.menu.edit'))
        ->assertSuccessful();

    $builtInKeys = collect($response->inertiaProps('builtInItems'))->pluck('key');

    expect($sidebar)->toContain('newsIndex()')
        ->and($sidebar)->toContain('t.value.news.title')
        ->and($builtInKeys->all())->toContain('news');
});
