<?php

use App\Models\PortalWebhook;
use App\Models\User;
use App\Models\UserGroup;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

function webhookAdministratorsGroup(): UserGroup
{
    return UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);
}

test('webhook settings are visible only to administrators and super admins', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $user = User::factory()->create();
    $admin = User::factory()->create([
        'user_group_id' => webhookAdministratorsGroup()->id,
    ]);
    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $this->actingAs($user)
        ->get(route('settings.webhooks.edit'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('settings.webhooks.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Webhooks')
            ->where('documentation.base_url', url('/portal-webhooks').'/{webhook_id}')
            ->where('documentation.users_index_url', url('/portal-webhooks').'/{webhook_id}/users')
            ->where('documentation.users_show_url', url('/portal-webhooks').'/{webhook_id}/users/{user_id}')
            ->has('availablePermissions')
            ->has('webhooks')
        );

    $this->actingAs($superAdmin)
        ->get(route('settings.webhooks.edit'))
        ->assertSuccessful();
});

test('administrators can create a permanent webhook with selected permissions', function () {
    $admin = User::factory()->create([
        'user_group_id' => webhookAdministratorsGroup()->id,
    ]);

    $response = $this->actingAs($admin)
        ->from(route('settings.webhooks.edit'))
        ->post(route('settings.webhooks.store'), [
            'name' => 'Portal Sync',
            'permissions' => [
                PortalWebhook::PERMISSION_USERS_READ,
                PortalWebhook::PERMISSION_PROJECTS_WRITE,
            ],
            'is_active' => true,
            'never_expires' => true,
            'expires_at' => null,
        ]);

    $webhook = PortalWebhook::query()->where('name', 'Portal Sync')->firstOrFail();

    $response->assertRedirect(route('settings.webhooks.edit'))
        ->assertSessionHas('inertia.flash_data.webhookToken.name', 'Portal Sync')
        ->assertSessionHas('inertia.flash_data.webhookToken.endpoint_url', $webhook->endpointUrl())
        ->assertSessionHas(
            'inertia.flash_data.webhookToken.signed_url',
            fn (string $value): bool => str_starts_with($value, $webhook->endpointUrl().'?token=')
        );

    expect($webhook->created_by_user_id)->toBe($admin->id)
        ->and($webhook->is_active)->toBeTrue()
        ->and($webhook->expires_at)->toBeNull()
        ->and($webhook->resolvedPermissions())->toBe([
            PortalWebhook::PERMISSION_USERS_READ,
            PortalWebhook::PERMISSION_PROJECTS_WRITE,
        ])
        ->and($webhook->token_prefix)->not->toBe('')
        ->and($webhook->token_hash)->not->toBe('');
});

test('administrators can create a webhook when permissions arrive as checkbox-style keyed values', function () {
    $admin = User::factory()->create([
        'user_group_id' => webhookAdministratorsGroup()->id,
    ]);

    $this->actingAs($admin)
        ->post(route('settings.webhooks.store'), [
            'name' => 'Checkbox webhook',
            'permissions' => [
                PortalWebhook::PERMISSION_USERS_READ => 'on',
                PortalWebhook::PERMISSION_PROJECTS_WRITE => '1',
            ],
            'is_active' => true,
            'never_expires' => true,
        ])
        ->assertRedirect();

    $webhook = PortalWebhook::query()->where('name', 'Checkbox webhook')->firstOrFail();

    expect($webhook->resolvedPermissions())->toBe([
        PortalWebhook::PERMISSION_USERS_READ,
        PortalWebhook::PERMISSION_PROJECTS_WRITE,
    ]);
});

test('webhook endpoint validates token expiration and updates last used time', function () {
    CarbonImmutable::setTestNow('2026-06-29 12:00:00');

    $firstUser = User::factory()->create([
        'name' => 'Alice',
        'email' => 'alice@example.com',
    ]);
    $secondUser = User::factory()->create([
        'name' => 'Bob',
        'email' => 'bob@example.com',
    ]);

    $webhook = PortalWebhook::factory()->create([
        'expires_at' => now()->addDay(),
        'permissions' => [PortalWebhook::PERMISSION_USERS_READ],
    ]);

    $plainToken = 'test-webhook-token';
    $webhook->issueToken($plainToken);

    $this->get(route('portal-webhooks.invoke', $webhook))
        ->assertUnauthorized();

    $this->get(route('portal-webhooks.invoke', $webhook).'?token='.$plainToken)
        ->assertOk()
        ->assertJsonPath('name', $webhook->name)
        ->assertJsonPath('permissions.0', PortalWebhook::PERMISSION_USERS_READ)
        ->assertJsonPath('endpoints.users.index', route('portal-webhooks.users.index', $webhook).'?token='.$plainToken)
        ->assertJsonFragment([
            'id' => $firstUser->id,
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ])
        ->assertJsonFragment([
            'id' => $secondUser->id,
            'name' => 'Bob',
            'email' => 'bob@example.com',
        ]);

    expect($webhook->fresh()->last_used_at?->toISOString())->not->toBeNull();

    $expiredWebhook = PortalWebhook::factory()->create([
        'expires_at' => now()->subMinute(),
    ]);
    $expiredWebhook->issueToken('expired-token');

    $this->get(route('portal-webhooks.invoke', $expiredWebhook).'?token=expired-token')
        ->assertStatus(410);

    CarbonImmutable::setTestNow();
});

test('webhook endpoint accepts bearer and x-webhook-token headers', function () {
    $webhook = PortalWebhook::factory()->create([
        'permissions' => [PortalWebhook::PERMISSION_USERS_READ],
    ]);

    $plainToken = 'header-webhook-token';
    $webhook->issueToken($plainToken);

    $this->withHeaders([
        'Authorization' => 'Bearer '.$plainToken,
    ])->get(route('portal-webhooks.invoke', $webhook))
        ->assertOk()
        ->assertJsonPath('id', $webhook->id);

    $this->withHeaders([
        'X-Webhook-Token' => $plainToken,
    ])->get(route('portal-webhooks.invoke', $webhook))
        ->assertOk()
        ->assertJsonPath('id', $webhook->id);
});

test('webhook users endpoint returns the users list when webhook has users read permission', function () {
    $webhook = PortalWebhook::factory()->create([
        'permissions' => [PortalWebhook::PERMISSION_USERS_READ],
    ]);
    $webhook->issueToken('users-webhook-token');

    $firstUser = User::factory()->create([
        'name' => 'Alice',
        'email' => 'alice@example.com',
    ]);
    $secondUser = User::factory()->create([
        'name' => 'Bob',
        'email' => 'bob@example.com',
    ]);

    $response = $this->get(route('portal-webhooks.users.index', $webhook).'?token=users-webhook-token');

    $response->assertSuccessful()
        ->assertJsonPath('webhook.id', $webhook->id)
        ->assertJsonPath('meta.total', User::query()->count())
        ->assertJsonFragment([
            'id' => $firstUser->id,
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ])
        ->assertJsonFragment([
            'id' => $secondUser->id,
            'name' => 'Bob',
            'email' => 'bob@example.com',
        ]);
});

test('webhook users endpoint is forbidden when webhook lacks users read permission', function () {
    $webhook = PortalWebhook::factory()->create([
        'permissions' => [PortalWebhook::PERMISSION_CHAT_READ],
    ]);
    $webhook->issueToken('chat-only-token');

    $this->get(route('portal-webhooks.users.index', $webhook).'?token=chat-only-token')
        ->assertForbidden()
        ->assertJsonPath('message', __('ui.webhooks.error_missing_permission'));
});

test('webhook settings page opens issued token details in a dialog', function () {
    $webhooksPage = file_get_contents(resource_path('js/pages/settings/Webhooks.vue'));
    $existingSectionPosition = strpos($webhooksPage, ':id="existingSectionId"');
    $createSectionPosition = strpos($webhooksPage, ':id="createSectionId"');
    $documentationSectionPosition = strpos($webhooksPage, ':id="documentationSectionId"');
    $webhookMetaPosition = strpos($webhooksPage, 'selectedWebhook.creator?.name ??');
    $webhookNameFieldPosition = strpos($webhooksPage, ':for="`webhook-name-${selectedWebhook.id}`"');

    expect($webhooksPage)->toContain('issuedWebhookDialogOpen')
        ->and($webhooksPage)->toContain('const applyIssuedWebhook = (webhook: IssuedWebhook): void =>')
        ->and($webhooksPage)->toContain('<Dialog')
        ->and($webhooksPage)->toContain(':open="issuedWebhookDialogOpen"')
        ->and($webhooksPage)->toContain('DialogContent class="sm:max-w-2xl"')
        ->and($webhooksPage)->toContain('const copyWebhookToken = async (): Promise<void> =>')
        ->and($webhooksPage)->toContain('closeIssuedWebhookDialog')
        ->and($webhooksPage)->toContain('onFlash: (flash: { webhookToken?: IssuedWebhook }) =>')
        ->and($webhooksPage)->toContain('t.webhooks.copy_token')
        ->and($webhooksPage)->toContain("const existingSectionId = 'webhook-existing';")
        ->and($webhooksPage)->toContain("const createSectionId = 'webhook-create';")
        ->and($webhooksPage)->toContain("const documentationSectionId = 'webhook-documentation';")
        ->and($webhooksPage)->toContain('const selectedWebhookId = ref<number | null>')
        ->and($webhooksPage)->toContain('const selectWebhook = (webhookId: number): void =>')
        ->and($webhooksPage)->toContain('sidebar-webhook-')
        ->and($webhooksPage)->toContain('class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]"')
        ->and($webhooksPage)->toContain('class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3"')
        ->and($webhooksPage)->toContain('class="min-w-0 space-y-1"')
        ->and($webhooksPage)->toContain('class="break-words text-sm text-muted-foreground"')
        ->and($webhooksPage)->toContain('props.documentation.base_url')
        ->and($webhooksPage)->toContain('documentation_endpoint_users_index_title');

    expect($existingSectionPosition)->not->toBeFalse()
        ->and($createSectionPosition)->not->toBeFalse()
        ->and($documentationSectionPosition)->not->toBeFalse()
        ->and($webhookMetaPosition)->not->toBeFalse()
        ->and($webhookNameFieldPosition)->not->toBeFalse()
        ->and($webhookMetaPosition)->toBeLessThan($webhookNameFieldPosition)
        ->and($existingSectionPosition)->toBeLessThan($createSectionPosition)
        ->and($createSectionPosition)->toBeLessThan($documentationSectionPosition);
});
