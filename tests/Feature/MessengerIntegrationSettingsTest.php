<?php

use App\Models\MessengerIntegration;
use App\Models\MessengerIntegrationGroupAccess;
use App\Models\User;
use App\Models\UserGroup;
use Inertia\Testing\AssertableInertia as Assert;

test('only super admin can open messenger integrations settings', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $administrators = UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);

    $admin = User::factory()->create([
        'user_group_id' => $administrators->id,
    ]);

    $this->actingAs($admin)
        ->get(route('settings.integrations.edit'))
        ->assertForbidden();

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $this->actingAs($superAdmin)
        ->get(route('settings.integrations.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Integrations')
            ->has('groups')
            ->has('integrations', 3)
            ->has('accessLevels', 3)
            ->where('superAdminAccessLevel', MessengerIntegration::ACCESS_FULL)
        );
});

test('settings page recreates missing default telephony integration automatically', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    MessengerIntegration::query()
        ->where('driver', MessengerIntegration::DRIVER_TELEPHONY)
        ->delete();

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $this->actingAs($superAdmin)
        ->get(route('settings.integrations.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('integrations', 3)
        );

    expect(MessengerIntegration::query()
        ->where('driver', MessengerIntegration::DRIVER_TELEPHONY)
        ->exists())->toBeTrue();
});

test('super admin can update messenger integration settings and group access', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $supportGroup = UserGroup::factory()->create([
        'name' => 'Support',
    ]);

    $salesGroup = UserGroup::factory()->create([
        'name' => 'Sales',
    ]);

    $integration = MessengerIntegration::query()->where('driver', MessengerIntegration::DRIVER_WHATSAPP_BUSINESS)->firstOrFail();

    $this->actingAs($superAdmin)
        ->patch(route('settings.integrations.update', $integration), [
            'name' => 'Primary WhatsApp',
            'is_active' => true,
            'settings' => [
                'api_url' => 'https://wazzup.example.test',
                'channel_id' => 'wa-01',
                'phone_number' => '+77000000000',
                'api_token' => 'secret-token',
            ],
            'group_accesses' => [
                [
                    'user_group_id' => $supportGroup->id,
                    'access_level' => MessengerIntegration::ACCESS_REPLY,
                ],
                [
                    'user_group_id' => $salesGroup->id,
                    'access_level' => MessengerIntegration::ACCESS_VIEW,
                ],
            ],
        ])
        ->assertRedirect();

    $integration->refresh();

    expect($integration->name)->toBe('Primary WhatsApp')
        ->and($integration->is_active)->toBeTrue()
        ->and($integration->settings)->toMatchArray([
            'api_url' => 'https://wazzup.example.test',
            'channel_id' => 'wa-01',
            'phone_number' => '+77000000000',
            'api_token' => 'secret-token',
        ])
        ->and($integration->updated_by_user_id)->toBe($superAdmin->id);

    expect(MessengerIntegrationGroupAccess::query()
        ->where('messenger_integration_id', $integration->id)
        ->where('user_group_id', $supportGroup->id)
        ->value('access_level'))
        ->toBe(MessengerIntegration::ACCESS_REPLY);

    expect(MessengerIntegrationGroupAccess::query()
        ->where('messenger_integration_id', $integration->id)
        ->where('user_group_id', $salesGroup->id)
        ->value('access_level'))
        ->toBe(MessengerIntegration::ACCESS_VIEW);
});

test('reply access is limited to the user who owns the messenger conversation while super admin keeps full access', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $integration = MessengerIntegration::query()->where('driver', MessengerIntegration::DRIVER_TELEGRAM)->firstOrFail();
    $group = UserGroup::factory()->create();

    $user = User::factory()->create([
        'user_group_id' => $group->id,
    ]);

    MessengerIntegrationGroupAccess::factory()->create([
        'messenger_integration_id' => $integration->id,
        'user_group_id' => $group->id,
        'access_level' => MessengerIntegration::ACCESS_REPLY,
    ]);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    expect($integration->accessLevelForUser($user))->toBe(MessengerIntegration::ACCESS_REPLY)
        ->and($integration->canUserView($user))->toBeTrue()
        ->and($integration->canUserReply($user, null))->toBeTrue()
        ->and($integration->canUserReply($user, $user->id))->toBeTrue()
        ->and($integration->canUserReply($user, $superAdmin->id))->toBeFalse()
        ->and($integration->accessLevelForUser($superAdmin))->toBe(MessengerIntegration::ACCESS_FULL)
        ->and($integration->canUserReply($superAdmin, $user->id))->toBeTrue();
});

test('super admin can update telephony integration settings', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $group = UserGroup::factory()->create([
        'name' => 'Call Center',
    ]);

    $integration = MessengerIntegration::query()
        ->where('driver', MessengerIntegration::DRIVER_TELEPHONY)
        ->firstOrFail();

    $this->actingAs($superAdmin)
        ->patch(route('settings.integrations.update', $integration), [
            'name' => 'Main Telephony',
            'is_active' => true,
            'settings' => [
                'provider_name' => 'Binotel',
                'api_url' => 'https://pbx.example.test',
                'account_id' => 'line-01',
                'phone_number' => '+77010002030',
                'extension_number' => '101',
                'api_token' => 'telephony-secret',
                'webhook_url' => 'https://crm369.test/api/telephony/webhook',
                'webhook_secret' => 'webhook-secret',
                'default_line' => 'Support queue',
                'outbound_caller_id' => '+77010002030',
                'responsible_mode' => 'call_receiver',
                'missed_call_mode' => 'create_activity',
                'recording_mode' => 'all_calls',
                'create_contact_for_unknown_calls' => true,
                'create_activity_for_missed_calls' => true,
                'log_incoming_calls' => true,
                'log_outgoing_calls' => true,
            ],
            'group_accesses' => [
                [
                    'user_group_id' => $group->id,
                    'access_level' => MessengerIntegration::ACCESS_REPLY,
                ],
            ],
        ])
        ->assertRedirect();

    $integration->refresh();

    expect($integration->name)->toBe('Main Telephony')
        ->and($integration->is_active)->toBeTrue()
        ->and($integration->settings)->toMatchArray([
            'provider_name' => 'Binotel',
            'api_url' => 'https://pbx.example.test',
            'account_id' => 'line-01',
            'phone_number' => '+77010002030',
            'extension_number' => '101',
            'api_token' => 'telephony-secret',
            'webhook_url' => 'https://crm369.test/api/telephony/webhook',
            'webhook_secret' => 'webhook-secret',
            'default_line' => 'Support queue',
            'outbound_caller_id' => '+77010002030',
            'responsible_mode' => 'call_receiver',
            'missed_call_mode' => 'create_activity',
            'recording_mode' => 'all_calls',
            'create_contact_for_unknown_calls' => true,
            'create_activity_for_missed_calls' => true,
            'log_incoming_calls' => true,
            'log_outgoing_calls' => true,
        ])
        ->and($integration->updated_by_user_id)->toBe($superAdmin->id);
});

test('integrations settings page uses a sidebar editor with a list overview', function () {
    $page = file_get_contents(resource_path('js/pages/settings/Integrations.vue'));

    expect($page)->toContain('const editorOpen = ref(false);')
        ->and($page)->toContain('const selectedIntegrationId = ref<number | null>(null);')
        ->and($page)->toContain('const openEditor = (integrationId: number): void => {')
        ->and($page)->toContain('<Sheet :open="editorOpen"')
        ->and($page)->toContain('t.integrations.list_title')
        ->and($page)->toContain('t.integrations.edit_sidebar')
        ->and($page)->toContain('telephony_connection_title')
        ->and($page)->toContain('telephony_routing_title')
        ->and($page)->toContain('telephony_automation_title')
        ->and($page)->toContain('t.value.integrations.telephony')
        ->and($page)->not->toContain('integrationSectionId');
});
