<?php

use App\Models\User;
use App\Models\UserLoginActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('security page is displayed', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);
    Features::passkeys([
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->where('canManagePasskeys', true)
            ->where('sessions', [])
            ->where('loginActivities', [])
            ->where('passkeys', [])
            ->where('canManageTwoFactor', true)
            ->where('twoFactorEnabled', false),
        );
});

test('security page requires password confirmation when enabled', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->create();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $response = $this->actingAs($user)
        ->get(route('security.edit'));

    $response->assertRedirect(route('password.confirm'));
});

test('security page renders without two factor when feature is disabled', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    config(['fortify.features' => []]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->where('canManagePasskeys', false)
            ->where('sessions', [])
            ->where('loginActivities', [])
            ->where('passkeys', [])
            ->where('canManageTwoFactor', false)
            ->missing('twoFactorEnabled')
            ->missing('requiresConfirmation'),
        );
});

test('security page includes current sessions and login activity data', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();
    $sessionId = 'current-security-session';

    app('session')->setId($sessionId);
    app('session')->start();

    DB::table('sessions')->insert([
        [
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ],
        [
            'id' => 'secondary-session',
            'user_id' => $user->id,
            'ip_address' => '192.168.0.25',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
            'payload' => 'payload',
            'last_activity' => now()->subMinutes(5)->timestamp,
        ],
    ]);

    UserLoginActivity::factory()->for($user)->create([
        'ip_address' => '203.0.113.55',
        'user_agent' => 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
        'browser' => 'Chrome',
        'platform' => 'Android',
        'device_type' => 'mobile',
        'device_signature' => hash('sha256', 'android-device'),
        'is_new_device' => true,
        'is_new_ip' => true,
        'logged_in_at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->has('sessions', 2)
            ->where('sessions.0.ip_address', '127.0.0.1')
            ->where('sessions.0.browser', 'Chrome')
            ->where('sessions.0.platform', 'Windows')
            ->where('sessions.1.device_type', 'mobile')
            ->has('loginActivities', 1)
            ->where('loginActivities.0.ip_address', '203.0.113.55')
            ->where('loginActivities.0.is_new_device', true)
            ->where('loginActivities.0.is_new_ip', true)
            ->where('loginActivities.0.device_type', 'mobile'),
        );
});

test('security page includes a back button to profile settings', function () {
    $securityPage = file_get_contents(resource_path('js/pages/settings/Security.vue'));

    expect($securityPage)->toContain('t.common.back')
        ->and($securityPage)->toContain('editProfile()')
        ->and($securityPage)->toContain('size="lg"');
});

test('security page includes password generator support', function () {
    $securityPage = file_get_contents(resource_path('js/pages/settings/Security.vue'));
    $generator = file_get_contents(resource_path('js/composables/usePasswordGenerator.ts'));

    expect($securityPage)->toContain('usePasswordGenerator')
        ->and($securityPage)->toContain('applyGeneratedPassword')
        ->and($securityPage)->toContain('t.common.generate_password')
        ->and($securityPage)->toContain('RefreshCw')
        ->and($securityPage)->toContain('form.password = generatedPassword')
        ->and($securityPage)->toContain(
            'form.password_confirmation = generatedPassword',
        )
        ->and($generator)->toContain('generatePassword')
        ->and($securityPage)->toContain('props.sessions.length')
        ->and($securityPage)->toContain('props.loginActivities.length')
        ->and($securityPage)->toContain('t.security.sessions_title')
        ->and($securityPage)->toContain('t.security.login_history_title');
});

test('settings layout uses shared settings navigation for all available tabs', function () {
    $settingsLayout = file_get_contents(resource_path('js/layouts/settings/Layout.vue'));
    $settingsTabs = file_get_contents(resource_path('js/components/SettingsTabs.vue'));
    $settingsNavigation = file_get_contents(resource_path('js/composables/useSettingsNavigation.ts'));
    $appSidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

    expect($settingsLayout)->toContain('<SettingsTabs />')
        ->and($settingsTabs)->toContain('useSettingsNavigation()')
        ->and($settingsTabs)->toContain(':aria-label="t.common.settings"');

    expect($settingsNavigation)->toContain("key: 'settings.users'")
        ->and($settingsNavigation)->toContain("key: 'settings.groups'")
        ->and($settingsNavigation)->toContain("key: 'settings.rights'")
        ->and($settingsNavigation)->toContain("key: 'settings.portal'")
        ->and($settingsNavigation)->toContain("key: 'settings.modules'")
        ->and($settingsNavigation)->toContain("key: 'settings.integrations'")
        ->and($settingsNavigation)->toContain("key: 'settings.logs'")
        ->and($settingsNavigation)->toContain("key: 'settings.webhooks'")
        ->and($settingsNavigation)->not->toContain("key: 'settings.api.documentation'")
        ->and($settingsNavigation)->not->toContain("key: 'settings.webhooks.documentation'")
        ->and($settingsNavigation)->toContain('page.props.auth.canViewUsers')
        ->and($settingsNavigation)->toContain('page.props.auth.isSuperAdmin')
        ->and($settingsNavigation)->toContain('page.props.auth.canManageWebhooks')
        ->and($settingsNavigation)->not->toContain('email_verified_at')
        ->and($appSidebar)->toContain('const settingsNavItems = useSettingsNavigation()')
        ->and($appSidebar)->toContain('const settingsItems = settingsNavItems.value');
});

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('security.edit'));
});
