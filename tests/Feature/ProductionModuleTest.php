<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users can open the production overview and detail sections', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('production.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('production/Index')
            ->where('activeSection', 'overview')
            ->has('sections', 8)
            ->where('sections.1', 'warehouses')
        );

    $this->actingAs($user)
        ->get(route('production.show', 'warehouses'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('production/Index')
            ->where('activeSection', 'warehouses')
            ->where('sections.7', 'quality-control')
        );
});

test('production is wired into the sidebar and built in menu items', function () {
    $user = User::factory()->create();

    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));
    $response = $this->actingAs($user)
        ->get(route('settings.menu.edit'))
        ->assertSuccessful();

    $builtInKeys = collect($response->inertiaProps('builtInItems'))->pluck('key');

    expect($sidebar)->toContain("isMenuItemVisible('production')")
        ->and($sidebar)->toContain('title: t.value.production.title')
        ->and($sidebar)->toContain('href: productionIndex()')
        ->and($sidebar)->toContain("href: showProductionSection('warehouses')")
        ->and($sidebar)->toContain("href: showProductionSection('quality-control')")
        ->and($builtInKeys->all())->toContain('production');
});
