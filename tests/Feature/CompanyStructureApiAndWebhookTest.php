<?php

use App\Models\ApiAccessToken;
use App\Models\PortalSetting;
use App\Models\PortalWebhook;
use App\Models\User;
use App\Models\UserGroup;

function companyStructureApiAdministratorsGroup(): UserGroup
{
    return UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);
}

function issueCompanyStructureApiTokenFor(User $user, array $permissions): string
{
    $plainTextToken = ApiAccessToken::generatePlainTextToken();

    ApiAccessToken::query()->create([
        'user_id' => $user->id,
        'name' => 'Company structure token',
        ...ApiAccessToken::tokenAttributes($plainTextToken),
        'permissions' => $permissions,
    ]);

    return $plainTextToken;
}

function companyStructureApiHeadersFor(string $token): array
{
    return [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ];
}

test('company structure api endpoints return the hierarchy and one selected node', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => companyStructureApiAdministratorsGroup()->id,
    ]);

    $chiefExecutive = User::factory()->create([
        'name' => 'Aruzhan',
        'last_name' => 'Sarsenova',
        'middle_name' => 'Bauyrzhanovna',
        'position' => 'Chief Executive Officer',
    ]);
    $manager = User::factory()->create([
        'name' => 'Timur',
        'last_name' => 'Aitbayev',
        'middle_name' => 'Maratovich',
        'position' => 'Operations Manager',
        'manager_id' => $chiefExecutive->id,
    ]);
    $staff = User::factory()->create([
        'name' => 'Dana',
        'last_name' => 'Abdullina',
        'middle_name' => 'Sergeevna',
        'position' => 'Account Executive',
        'manager_id' => $manager->id,
    ]);

    $token = issueCompanyStructureApiTokenFor($admin, [
        ApiAccessToken::PERMISSION_COMPANY_STRUCTURE_READ,
    ]);

    $this->withHeaders(companyStructureApiHeadersFor($token))
        ->getJson(route('api.v1.company-structure.index'))
        ->assertOk()
        ->assertJsonPath('stats.total_users', 4)
        ->assertJsonFragment(['full_name' => 'Aruzhan Sarsenova Bauyrzhanovna'])
        ->assertJsonFragment(['full_name' => 'Timur Aitbayev Maratovich'])
        ->assertJsonFragment(['full_name' => 'Dana Abdullina Sergeevna']);

    $this->withHeaders(companyStructureApiHeadersFor($token))
        ->getJson(route('api.v1.company-structure.show', $manager))
        ->assertOk()
        ->assertJsonPath('data.full_name', 'Timur Aitbayev Maratovich')
        ->assertJsonPath('data.manager.full_name', 'Aruzhan Sarsenova Bauyrzhanovna')
        ->assertJsonPath('data.subordinates.0.full_name', 'Dana Abdullina Sergeevna')
        ->assertJsonPath('ancestors.0.full_name', 'Aruzhan Sarsenova Bauyrzhanovna');

    expect($staff->refresh()->manager_id)->toBe($manager->id);
});

test('company structure api enforces permission and disabled module state', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => companyStructureApiAdministratorsGroup()->id,
    ]);

    $withoutPermissionToken = issueCompanyStructureApiTokenFor($admin, [
        ApiAccessToken::PERMISSION_PROFILE_READ,
    ]);

    $this->withHeaders(companyStructureApiHeadersFor($withoutPermissionToken))
        ->getJson(route('api.v1.company-structure.index'))
        ->assertForbidden();

    PortalSetting::current()->update([
        'disabled_modules' => ['company-structure'],
    ]);

    $readToken = issueCompanyStructureApiTokenFor($admin, [
        ApiAccessToken::PERMISSION_COMPANY_STRUCTURE_READ,
    ]);

    $this->withHeaders(companyStructureApiHeadersFor($readToken))
        ->getJson(route('api.v1.company-structure.index'))
        ->assertNotFound();
});

test('company structure webhook endpoints and settings documentation expose the new module', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => companyStructureApiAdministratorsGroup()->id,
    ]);

    $chiefExecutive = User::factory()->create([
        'name' => 'Aruzhan',
        'last_name' => 'Sarsenova',
        'middle_name' => 'Bauyrzhanovna',
        'position' => 'Chief Executive Officer',
    ]);
    $manager = User::factory()->create([
        'name' => 'Timur',
        'last_name' => 'Aitbayev',
        'middle_name' => 'Maratovich',
        'position' => 'Operations Manager',
        'manager_id' => $chiefExecutive->id,
    ]);

    $webhook = PortalWebhook::factory()->create([
        'created_by_user_id' => $admin->id,
        'permissions' => [PortalWebhook::PERMISSION_COMPANY_STRUCTURE_READ],
    ]);
    $webhook->issueToken('company-structure-webhook-token');

    $webhooksPage = $this->actingAs($admin)
        ->get(route('settings.webhooks.documentation.edit'))
        ->assertSuccessful();

    expect($webhooksPage->inertiaProps('documentation.company_structure_index_url'))
        ->toBe(url('/portal-webhooks').'/{webhook_id}/company-structure')
        ->and($webhooksPage->inertiaProps('documentation.company_structure_show_url'))
        ->toBe(url('/portal-webhooks').'/{webhook_id}/company-structure/users/{user_id}');

    $webhooksSettingsPage = $this->actingAs($admin)
        ->get(route('settings.webhooks.edit'))
        ->assertSuccessful();

    expect(collect($webhooksSettingsPage->inertiaProps('availablePermissions'))->pluck('key')->all())
        ->toContain(PortalWebhook::PERMISSION_COMPANY_STRUCTURE_READ);

    $apiDocsPage = $this->actingAs($admin)
        ->get(route('settings.api.documentation.edit'))
        ->assertSuccessful();

    expect(collect($apiDocsPage->inertiaProps('documentation'))->pluck('title')->all())
        ->toContain(__('ui.api.section_company_structure'));

    $this->get(route('portal-webhooks.invoke', $webhook).'?token=company-structure-webhook-token')
        ->assertOk()
        ->assertJsonPath('company_structure.stats.total_users', 3)
        ->assertJsonPath(
            'endpoints.company_structure.index',
            route('portal-webhooks.company-structure.index', $webhook).'?token=company-structure-webhook-token',
        )
        ->assertJsonPath(
            'endpoints.company_structure.show_template',
            route('portal-webhooks.company-structure.show', [
                'portalWebhook' => $webhook,
                'user' => '__USER_ID__',
            ]).'?token=company-structure-webhook-token',
        );

    $this->get(route('portal-webhooks.company-structure.index', $webhook).'?token=company-structure-webhook-token')
        ->assertOk()
        ->assertJsonPath('stats.total_users', 3)
        ->assertJsonFragment(['full_name' => 'Aruzhan Sarsenova Bauyrzhanovna'])
        ->assertJsonFragment(['full_name' => 'Timur Aitbayev Maratovich']);

    $this->get(route('portal-webhooks.company-structure.show', [$webhook, $manager]).'?token=company-structure-webhook-token')
        ->assertOk()
        ->assertJsonPath('data.full_name', 'Timur Aitbayev Maratovich')
        ->assertJsonPath('data.manager.full_name', 'Aruzhan Sarsenova Bauyrzhanovna');
});
