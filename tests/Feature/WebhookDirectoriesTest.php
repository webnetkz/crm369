<?php

use App\Models\PortalWebhook;
use App\Models\ReferenceDirectory;
use App\Models\ReferenceDirectoryRecord;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function directoriesWebhookSuperAdmin(): User
{
    config(['admin.super_admin_email' => 'directories-webhook-admin@example.com']);

    return User::factory()->create([
        'email' => 'directories-webhook-admin@example.com',
        'email_verified_at' => now(),
    ]);
}

/**
 * @return array<string, mixed>
 */
function directoriesWebhookPayload(string $name = 'Partners', string $slug = 'partners'): array
{
    return [
        'name' => $name,
        'slug' => $slug,
        'description' => 'Partner reference directory.',
        'columns' => [
            [
                'label' => 'Partner name',
                'key' => 'partner_name',
                'type' => 'text',
                'is_required' => true,
            ],
            [
                'label' => 'Priority',
                'key' => 'priority',
                'type' => 'number',
                'is_required' => false,
            ],
            [
                'label' => 'Verified',
                'key' => 'verified',
                'type' => 'boolean',
                'is_required' => true,
            ],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function directoriesWebhookRecordPayload(
    string $partnerName = 'Contoso',
    string $priority = '3',
    bool $verified = true,
): array {
    return [
        'values' => [
            'partner_name' => $partnerName,
            'priority' => $priority,
            'verified' => $verified,
        ],
    ];
}

test('webhook settings and documentation expose directories endpoints and permissions', function () {
    $admin = directoriesWebhookSuperAdmin();

    $this->actingAs($admin)
        ->get(route('settings.webhooks.documentation.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/WebhookDocumentation')
            ->where('documentation.directories_index_url', url('/portal-webhooks').'/{webhook_id}/directories')
            ->where('documentation.directories_show_url', url('/portal-webhooks').'/{webhook_id}/directories/{directory_id}')
            ->where('documentation.directory_records_store_url', url('/portal-webhooks').'/{webhook_id}/directories/{directory_id}/records')
            ->where('documentation.directory_records_update_url', url('/portal-webhooks').'/{webhook_id}/directories/{directory_id}/records/{record_id}')
            ->where('documentation.directory_records_destroy_url', url('/portal-webhooks').'/{webhook_id}/directories/{directory_id}/records/{record_id}')
        );

    $this->actingAs($admin)
        ->get(route('settings.webhooks.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Webhooks')
            ->where('availablePermissions', fn ($permissions): bool => collect($permissions)->contains(
                fn (array $permission): bool => $permission['key'] === PortalWebhook::PERMISSION_DIRECTORIES_READ
            ) && collect($permissions)->contains(
                fn (array $permission): bool => $permission['key'] === PortalWebhook::PERMISSION_DIRECTORIES_WRITE
            ))
        );
});

test('webhook directories read permission exposes directories in invoke payload and endpoints', function () {
    $directory = ReferenceDirectory::factory()->create([
        'name' => 'Vendors',
        'slug' => 'vendors',
        'columns' => [
            [
                'label' => 'Vendor',
                'key' => 'vendor',
                'type' => 'text',
                'is_required' => true,
            ],
        ],
    ]);

    ReferenceDirectoryRecord::factory()->create([
        'reference_directory_id' => $directory->id,
        'values' => [
            'vendor' => 'Tailspin Toys',
        ],
    ]);

    $webhook = PortalWebhook::factory()->create([
        'permissions' => [PortalWebhook::PERMISSION_DIRECTORIES_READ],
    ]);
    $webhook->issueToken('directories-read-token');

    $this->get(route('portal-webhooks.invoke', $webhook).'?token=directories-read-token')
        ->assertOk()
        ->assertJsonPath(
            'endpoints.directories.index',
            route('portal-webhooks.directories.index', $webhook).'?token=directories-read-token',
        )
        ->assertJsonPath(
            'endpoints.directories.show_template',
            route('portal-webhooks.directories.show', [
                'portalWebhook' => $webhook,
                'referenceDirectory' => '__DIRECTORY_ID__',
            ]).'?token=directories-read-token',
        )
        ->assertJsonFragment([
            'id' => $directory->id,
            'name' => 'Vendors',
            'slug' => 'vendors',
        ]);

    $this->get(route('portal-webhooks.directories.index', $webhook).'?token=directories-read-token')
        ->assertOk()
        ->assertJsonPath('webhook.id', $webhook->id)
        ->assertJsonPath('data.0.id', $directory->id)
        ->assertJsonPath('data.0.records_count', 1);

    $this->get(route('portal-webhooks.directories.show', [$webhook, $directory]).'?token=directories-read-token')
        ->assertOk()
        ->assertJsonPath('data.id', $directory->id)
        ->assertJsonPath('data.records.0.values.vendor', 'Tailspin Toys');
});

test('webhook directories write permission can create update and delete directories and records', function () {
    $webhook = PortalWebhook::factory()->create([
        'permissions' => [PortalWebhook::PERMISSION_DIRECTORIES_WRITE],
    ]);
    $webhook->issueToken('directories-write-token');

    $createResponse = $this->postJson(
        route('portal-webhooks.directories.store', $webhook).'?token=directories-write-token',
        directoriesWebhookPayload(),
    )
        ->assertCreated()
        ->assertJsonPath('message', __('ui.directories.created_success'))
        ->assertJsonPath('data.slug', 'partners');

    $directoryId = $createResponse->json('data.id');

    $createRecordResponse = $this->postJson(
        route('portal-webhooks.directories.records.store', [$webhook, $directoryId]).'?token=directories-write-token',
        directoriesWebhookRecordPayload(),
    )
        ->assertCreated()
        ->assertJsonPath('message', __('ui.directories.record_created_success'))
        ->assertJsonPath('data.values.partner_name', 'Contoso')
        ->assertJsonPath('data.values.priority', 3)
        ->assertJsonPath('data.values.verified', true);

    $recordId = $createRecordResponse->json('data.id');

    $this->patchJson(
        route('portal-webhooks.directories.records.update', [$webhook, $directoryId, $recordId]).'?token=directories-write-token',
        directoriesWebhookRecordPayload(partnerName: 'Fabrikam', priority: '8', verified: false),
    )
        ->assertOk()
        ->assertJsonPath('message', __('ui.directories.record_updated_success'))
        ->assertJsonPath('data.values.partner_name', 'Fabrikam')
        ->assertJsonPath('data.values.priority', 8)
        ->assertJsonPath('data.values.verified', false);

    $this->patchJson(
        route('portal-webhooks.directories.update', [$webhook, $directoryId]).'?token=directories-write-token',
        directoriesWebhookPayload(name: 'Strategic Partners', slug: 'strategic-partners'),
    )
        ->assertOk()
        ->assertJsonPath('message', __('ui.directories.updated_success'))
        ->assertJsonPath('data.name', 'Strategic Partners')
        ->assertJsonPath('data.slug', 'strategic-partners');

    $this->deleteJson(
        route('portal-webhooks.directories.records.destroy', [$webhook, $directoryId, $recordId]).'?token=directories-write-token',
    )
        ->assertOk()
        ->assertJsonPath('message', __('ui.directories.record_deleted_success'))
        ->assertJsonPath('data.id', $recordId);

    $this->deleteJson(
        route('portal-webhooks.directories.destroy', [$webhook, $directoryId]).'?token=directories-write-token',
    )
        ->assertOk()
        ->assertJsonPath('message', __('ui.directories.deleted_success'))
        ->assertJsonPath('data.id', $directoryId);

    expect(ReferenceDirectory::query()->whereKey($directoryId)->exists())->toBeFalse();
});

test('webhook directories write endpoints require write permission', function () {
    $webhook = PortalWebhook::factory()->create([
        'permissions' => [PortalWebhook::PERMISSION_DIRECTORIES_READ],
    ]);
    $webhook->issueToken('directories-read-only-token');

    $this->postJson(
        route('portal-webhooks.directories.store', $webhook).'?token=directories-read-only-token',
        directoriesWebhookPayload(),
    )->assertForbidden();
});
