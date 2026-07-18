<?php

use App\Models\ApiAccessToken;
use App\Models\EdoDocument;
use App\Models\PortalForm;
use App\Models\PortalSetting;
use App\Models\PortalWebhook;
use App\Models\User;
use App\Models\Warehouse;
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
        'disabled_modules' => ['chats', 'contacts', 'funnels', 'knowledge-bases', 'production', 'warehouses', 'tsd'],
    ]);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $response = $this->actingAs($superAdmin)->get(route('dashboard'));

    expect($response->inertiaProps('menu.hiddenItems'))
        ->toContain('chats')
        ->toContain('contacts')
        ->toContain('funnels')
        ->toContain('knowledge-bases')
        ->toContain('production')
        ->toContain('warehouses')
        ->toContain('tsd');

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
    ['conferences', 'conferences.index'],
    ['calendar', 'calendar.index'],
    ['knowledge-bases', 'knowledge-bases.index'],
    ['funnels', 'funnels.index'],
    ['forms', 'forms.index'],
    ['contacts', 'contacts.index'],
    ['edo', 'edo.index'],
    ['files', 'files.index'],
    ['production', 'production.index'],
    ['warehouses', 'warehouses.index'],
    ['tsd', 'tsd.index'],
    ['equipment', 'equipment.index'],
    ['api', 'settings.api.edit'],
    ['api', 'settings.api.documentation.edit'],
    ['integrations', 'settings.integrations.edit'],
    ['one-c', 'settings.one-c.edit'],
    ['business-processes', 'settings.business-processes.index'],
    ['webhooks', 'settings.webhooks.edit'],
    ['webhooks', 'settings.webhooks.documentation.edit'],
]);

test('disabled forms module also blocks public forms', function () {
    PortalSetting::current()->update([
        'disabled_modules' => ['forms'],
    ]);

    $form = PortalForm::factory()->create();

    $this->get(route('forms.public.show', $form->public_token))
        ->assertNotFound();
});

test('disabled warehouses module also blocks warehouse detail pages', function () {
    PortalSetting::current()->update([
        'disabled_modules' => ['warehouses'],
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $warehouse = Warehouse::factory()->create();

    $this->actingAs($user)
        ->get(route('warehouses.show', $warehouse))
        ->assertNotFound();
});

test('disabled edo module also blocks public signing pages', function () {
    PortalSetting::current()->update([
        'disabled_modules' => ['edo'],
    ]);

    $document = EdoDocument::factory()->pendingSignature()->create();

    $this->get(route('edo.public.show', [
        'edoDocument' => $document->public_token,
        'signature' => 'invalid',
        'expires' => now()->addHours(12)->timestamp,
    ]))->assertNotFound();
});

test('disabled api module blocks api endpoints even for valid tokens', function () {
    config(['admin.super_admin_email' => 'super@example.com']);
    config(['app.debug' => true]);

    PortalSetting::current()->update([
        'disabled_modules' => ['api'],
    ]);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $plainTextToken = ApiAccessToken::generatePlainTextToken();

    ApiAccessToken::query()->create([
        'user_id' => $superAdmin->id,
        'name' => 'Disabled API token',
        ...ApiAccessToken::tokenAttributes($plainTextToken),
        'permissions' => [ApiAccessToken::PERMISSION_PROFILE_READ],
    ]);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plainTextToken,
        'Accept' => 'application/json',
    ])->getJson(route('api.v1.profile.show'))
        ->assertNotFound()
        ->assertJsonPath('message', 'Not Found')
        ->assertJsonMissingPath('exception')
        ->assertJsonMissingPath('file')
        ->assertJsonMissingPath('line')
        ->assertJsonMissingPath('trace');
});

test('disabled api module takes precedence over token management authorization', function () {
    PortalSetting::current()->update([
        'disabled_modules' => ['api'],
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('settings.api.tokens.store'), [
            'name' => 'Blocked token',
            'permissions' => [ApiAccessToken::PERMISSION_PROFILE_READ],
            'never_expires' => true,
        ])
        ->assertNotFound();
});

test('disabled integrations module blocks integrations api endpoints', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    PortalSetting::current()->update([
        'disabled_modules' => ['integrations'],
    ]);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $plainTextToken = ApiAccessToken::generatePlainTextToken();

    ApiAccessToken::query()->create([
        'user_id' => $superAdmin->id,
        'name' => 'Disabled integrations token',
        ...ApiAccessToken::tokenAttributes($plainTextToken),
        'permissions' => [ApiAccessToken::PERMISSION_INTEGRATIONS_READ],
    ]);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plainTextToken,
        'Accept' => 'application/json',
    ])->getJson(route('api.v1.integrations.index'))
        ->assertNotFound();
});

test('disabled webhooks module blocks webhook endpoints even for valid tokens', function () {
    PortalSetting::current()->update([
        'disabled_modules' => ['webhooks'],
    ]);

    $webhook = PortalWebhook::factory()->create([
        'permissions' => [PortalWebhook::PERMISSION_USERS_READ],
    ]);

    $plainTextToken = 'disabled-webhook-token';
    $webhook->issueToken($plainTextToken);

    $this->get(route('portal-webhooks.invoke', $webhook).'?token='.$plainTextToken)
        ->assertNotFound();
});

test('disabled webhooks module takes precedence over webhook authorization', function () {
    PortalSetting::current()->update([
        'disabled_modules' => ['webhooks'],
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('settings.webhooks.edit'))
        ->assertNotFound();
});

test('module settings page uses instant switch toggles instead of checkboxes', function () {
    $moduleSettingsPage = file_get_contents(resource_path('js/pages/settings/Modules.vue'));

    expect($moduleSettingsPage)->toContain('role="switch"')
        ->and($moduleSettingsPage)->toContain('@click="toggleModule(module.key)"')
        ->and($moduleSettingsPage)->toContain('form.patch(update.url()')
        ->and($moduleSettingsPage)->not->toContain('components/ui/checkbox');
});
