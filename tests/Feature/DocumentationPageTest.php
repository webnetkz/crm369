<?php

use App\Models\PortalSetting;
use App\Models\User;
use App\Models\UserGroup;
use Inertia\Testing\AssertableInertia as Assert;

function documentationAdministratorsGroup(): UserGroup
{
    return UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);
}

test('documentation page is available to verified users with api access', function () {
    PortalSetting::current()->update([
        'disabled_modules' => [],
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('documentation.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documentation/Index')
            ->where('sections.api', true)
            ->where('sections.webhooks', false)
            ->has('apiBaseUrl')
            ->has('apiDocumentation')
            ->where('webhookDocumentation', null)
        );
});

test('documentation page exposes webhook section to administrators', function () {
    PortalSetting::current()->update([
        'disabled_modules' => [],
    ]);

    $administrator = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => documentationAdministratorsGroup()->id,
    ]);

    $this->actingAs($administrator)
        ->get(route('documentation.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documentation/Index')
            ->where('sections.api', true)
            ->where('sections.webhooks', true)
            ->has('apiDocumentation')
            ->where(
                'webhookDocumentation.base_url',
                url('/portal-webhooks').'/{webhook_id}',
            )
            ->where(
                'webhookDocumentation.users_index_url',
                url('/portal-webhooks').'/{webhook_id}/users',
            )
        );
});

test('documentation page is forbidden when no documentation sections are available', function () {
    PortalSetting::current()->update([
        'disabled_modules' => ['api', 'webhooks'],
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('documentation.index'))
        ->assertForbidden();
});

test('documentation workspace is wired into the sidebar and includes the return logo', function () {
    $documentationPage = file_get_contents(resource_path('js/pages/documentation/Index.vue'));
    $appSidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

    expect($documentationPage)->toContain('<AppLogo />')
        ->and($documentationPage)->toContain('const activeSection = ref<DocumentationSectionKey>(initialSection())')
        ->and($documentationPage)->toContain('{{ t.documentation.back_to_platform }}')
        ->and($documentationPage)->toContain('{{ t.documentation.sections_label }}')
        ->and($documentationPage)->toContain(':title="t.settings.api_documentation"')
        ->and($documentationPage)->toContain(':title="t.settings.webhooks_documentation"')
        ->and($appSidebar)->toContain("key: 'documentation'")
        ->and($appSidebar)->toContain('opensInNewTab: true')
        ->and($appSidebar)->toContain('href: documentationIndex.url()');
});
