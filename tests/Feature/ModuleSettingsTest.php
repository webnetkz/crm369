<?php

use App\Models\PortalForm;
use App\Models\PortalSetting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('only super admin can open module settings', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $user = User::factory()->create();
    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $this->actingAs($user)
        ->get(route('settings.modules.edit'))
        ->assertForbidden();

    $this->actingAs($superAdmin)
        ->get(route('settings.modules.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Modules')
            ->has('modules', count(PortalSetting::availableModuleKeys()))
            ->where('disabledModules', [])
        );
});

test('super admin can disable selected modules', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $this->actingAs($superAdmin)
        ->patch(route('settings.modules.update'), [
            'disabled_modules' => ['chats', 'contacts', 'contacts', 'funnels'],
        ])
        ->assertRedirect();

    expect(PortalSetting::current()->disabledModules())
        ->toBe(['chats', 'funnels', 'contacts']);

    $this->actingAs($superAdmin)
        ->get(route('settings.modules.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('disabledModules', ['chats', 'funnels', 'contacts'])
            ->where('modules.2.key', 'chats')
            ->where('modules.2.is_enabled', false)
        );
});

test('disabled modules are hidden in shared props', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    PortalSetting::current()->update([
        'disabled_modules' => ['chats', 'contacts', 'funnels', 'knowledge-bases'],
    ]);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $response = $this->actingAs($superAdmin)->get(route('dashboard'));

    expect($response->inertiaProps('menu.hiddenItems'))
        ->toContain('chats')
        ->toContain('contacts')
        ->toContain('funnels')
        ->toContain('knowledge-bases');

    expect($response->inertiaProps('auth.canAccessContacts'))->toBeFalse()
        ->and($response->inertiaProps('auth.canManageKnowledgeBases'))->toBeFalse()
        ->and($response->inertiaProps('auth.canAccessFunnels'))->toBeFalse()
        ->and($response->inertiaProps('chat.unreadCount'))->toBe(0)
        ->and($response->inertiaProps('menu.knowledgeBases'))->toBe([]);
});

test('disabled module routes return not found', function (string $module, string $routeName) {
    config(['admin.super_admin_email' => 'super@example.com']);

    PortalSetting::current()->update([
        'disabled_modules' => [$module],
    ]);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $this->actingAs($superAdmin)
        ->get(route($routeName))
        ->assertNotFound();
})->with([
    ['news', 'news.index'],
    ['projects', 'projects.index'],
    ['chats', 'chats.index'],
    ['knowledge-bases', 'knowledge-bases.index'],
    ['funnels', 'funnels.index'],
    ['forms', 'forms.index'],
    ['contacts', 'contacts.index'],
    ['files', 'files.index'],
]);

test('disabled forms module also blocks public forms', function () {
    PortalSetting::current()->update([
        'disabled_modules' => ['forms'],
    ]);

    $form = PortalForm::factory()->create();

    $this->get(route('forms.public.show', $form->public_token))
        ->assertNotFound();
});
