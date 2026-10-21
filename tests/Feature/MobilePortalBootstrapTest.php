<?php

use App\Models\MobileAccessToken;
use App\Models\MobileDevice;
use App\Models\PortalSetting;
use App\Models\User;
use App\Models\UserGroup;
use App\Support\FirebaseAccessTokenProvider;
use App\Support\FirebaseCloudMessagingClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function mobileBootstrapToken(User $user, string $deviceId = 'android-bootstrap-device'): string
{
    return MobileAccessToken::issueToUser($user, $deviceId)['plain_text_token'];
}

test('the public portal endpoint identifies a CRM369 portal without exposing push options by default', function () {
    PortalSetting::current()->update(['company_name' => 'Teez CRM']);

    $this->getJson(route('mobile.v1.portal'))
        ->assertSuccessful()
        ->assertJsonPath('data.product', 'crm369')
        ->assertJsonPath('data.api_version', 1)
        ->assertJsonPath('data.name', 'Teez CRM')
        ->assertJsonPath('data.web_session', true)
        ->assertJsonPath('data.push.enabled', false)
        ->assertJsonPath('data.push.firebase', null)
        ->assertJsonMissingPath('data.enabled_modules');
});

test('the public portal endpoint exposes Firebase client options only when delivery is configured', function () {
    config([
        'services.fcm.project_id' => 'crm369-test-project',
        'services.fcm.android.application_id' => '1:1234567890:android:abcdef',
        'services.fcm.android.api_key' => 'test-client-api-key',
        'services.fcm.android.sender_id' => '1234567890',
    ]);

    $provider = Mockery::mock(FirebaseAccessTokenProvider::class);
    $provider->shouldReceive('isConfigured')->andReturnTrue();
    $provider->shouldReceive('projectId')->andReturn('crm369-test-project');
    app()->instance(FirebaseAccessTokenProvider::class, $provider);

    $this->getJson(route('mobile.v1.portal'))
        ->assertSuccessful()
        ->assertJsonPath('data.push.enabled', true)
        ->assertJsonPath('data.push.firebase.project_id', 'crm369-test-project')
        ->assertJsonPath('data.push.firebase.application_id', '1:1234567890:android:abcdef')
        ->assertJsonPath('data.push.firebase.api_key', 'test-client-api-key')
        ->assertJsonPath('data.push.firebase.sender_id', '1234567890');
});

test('guest mobile requests follow the device language header', function () {
    PortalSetting::current()->update(['default_language' => 'ru']);

    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->getJson(route('mobile.v1.portal'))
        ->assertSuccessful();

    expect(app()->getLocale())->toBe('en');

    $this->withHeader('Accept-Language', 'kk-KZ,ru;q=0.8')
        ->getJson(route('mobile.v1.portal'))
        ->assertSuccessful();

    expect(app()->getLocale())->toBe('ru');

    PortalSetting::current()->update(['default_language' => 'en']);

    $this->withHeader('Accept-Language', 'de-DE')
        ->getJson(route('mobile.v1.portal'))
        ->assertSuccessful();

    expect(app()->getLocale())->toBe('en');
});

test('authenticated mobile requests follow the language selected by the user', function () {
    $user = User::factory()->create([
        'language' => 'en',
        'has_selected_language' => true,
    ]);

    $this->withToken(mobileBootstrapToken($user))
        ->withHeader('Accept-Language', 'ru-RU')
        ->getJson(route('mobile.v1.bootstrap'))
        ->assertSuccessful()
        ->assertJsonPath('data.user.language', 'en');

    expect(app()->getLocale())->toBe('en');
});

test('the bootstrap endpoint returns the user abilities menu and counters', function () {
    $user = User::factory()->create();

    $this->withToken(mobileBootstrapToken($user))
        ->getJson(route('mobile.v1.bootstrap'))
        ->assertSuccessful()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.abilities.canAccessChats', true)
        ->assertJsonPath('data.abilities.canAccessProjects', true)
        ->assertJsonPath('data.abilities.canManageNews', false)
        ->assertJsonPath('data.counters.notifications', 0)
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email', 'language'],
                'abilities' => ['isSuperAdmin', 'canAccessProjects', 'canAccessFunnels', 'canManageNews'],
                'portal' => ['name', 'url', 'logo_url', 'default_language', 'enabled_modules'],
                'menu' => ['hiddenItems', 'customItems', 'knowledgeBases', 'order'],
                'counters' => ['notifications', 'chats'],
                'push' => ['enabled', 'provider', 'firebase'],
                'server_time',
            ],
        ]);
});

test('the bootstrap endpoint reflects disabled modules and restricted groups', function () {
    PortalSetting::current()->update(['disabled_modules' => ['news']]);

    $group = UserGroup::factory()->create([
        'permissions' => UserGroup::normalizePermissionsWithConfiguredModules([], ['chats', 'contacts']),
    ]);
    $user = User::factory()->create(['user_group_id' => $group->id]);

    $response = $this->withToken(mobileBootstrapToken($user))
        ->getJson(route('mobile.v1.bootstrap'))
        ->assertSuccessful()
        ->assertJsonPath('data.abilities.canAccessNews', false)
        ->assertJsonPath('data.abilities.canAccessChats', false)
        ->assertJsonPath('data.abilities.canAccessContacts', false)
        ->assertJsonPath('data.counters.chats', 0);

    expect($response->json('data.portal.enabled_modules'))->not->toContain('news');
});

test('the bootstrap endpoint requires a mobile session', function () {
    $this->getJson(route('mobile.v1.bootstrap'))->assertUnauthorized();
});

test('the current device can disable push notifications', function () {
    $user = User::factory()->create();
    $token = mobileBootstrapToken($user, 'android-push-device');
    $device = MobileDevice::factory()->create([
        'user_id' => $user->id,
        'device_id' => 'android-push-device',
    ]);

    $this->withToken($token)
        ->deleteJson(route('mobile.v1.device.destroy'), [
            'device_id' => 'android-push-device',
            'fcm_token' => $device->fcm_token,
        ])
        ->assertSuccessful();

    expect($device->fresh()->disabled_at)->not->toBeNull();
});

test('push payloads identify the portal that sent them', function () {
    config(['app.url' => 'https://crm.example.test/']);

    $provider = Mockery::mock(FirebaseAccessTokenProvider::class);
    $provider->shouldReceive('isConfigured')->andReturnTrue();
    $provider->shouldReceive('projectId')->andReturn('crm369-test-project');
    $provider->shouldReceive('accessToken')->andReturn('oauth-access-token');

    Http::fake([
        'https://fcm.googleapis.com/*' => Http::response(['name' => 'projects/test/messages/1']),
    ]);

    $device = MobileDevice::factory()->create(['fcm_token' => 'registered-fcm-token']);

    (new FirebaseCloudMessagingClient($provider))->send($device, [
        'title' => 'Новая задача',
        'body' => 'Проверьте срок выполнения',
        'type' => 'system',
        'action_path' => '/projects/tasks/42',
        'entity_id' => 42,
    ]);

    Http::assertSent(fn (Request $request): bool => $request['message']['data']['portal_url'] === 'https://crm.example.test');
});
