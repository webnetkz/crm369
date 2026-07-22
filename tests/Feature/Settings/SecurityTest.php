<?php

use App\Models\SecurityAudit;
use App\Models\User;
use App\Models\UserLoginActivity;
use Illuminate\Support\Facades\Cache;
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
            ->where('securityAudit.latest', null)
            ->where('securityAudit.history', [])
            ->where('securityAudit.manualDefaults.unique_password', false)
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
            ->where('securityAudit.latest', null)
            ->where('securityAudit.history', [])
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

test('security audit can be run from the security settings checklist', function () {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);
    Features::passkeys([
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this
        ->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from(route('security.edit'))
        ->post(route('security.audits.store'), [
            'manual' => [
                'unique_password' => true,
                'recovery_codes_stored' => true,
                'sessions_reviewed' => true,
                'device_protected' => true,
                'phishing_ready' => true,
            ],
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    $audit = SecurityAudit::query()->whereBelongsTo($user)->sole();
    $checks = collect($audit->checks)->keyBy('key');

    $this->assertModelExists($audit);

    expect($audit->total_count)->toBe(12)
        ->and($audit->score)->toBeGreaterThanOrEqual(90)
        ->and($audit->risk_level)->toBe('protected')
        ->and($checks)->toHaveKeys([
            'email_verified',
            'two_factor_enabled',
            'passkey_registered',
            'recovery_codes_available',
            'active_sessions',
            'recent_login_alerts',
            'api_tokens',
            'unique_password',
            'recovery_codes_stored',
            'sessions_reviewed',
            'device_protected',
            'phishing_ready',
        ])
        ->and($checks->get('two_factor_enabled')['status'])->toBe('passed')
        ->and($checks->get('passkey_registered')['status'])->toBe('warning')
        ->and($checks->get('unique_password')['status'])->toBe('passed');
});

test('security audit records risks from the manual checklist', function () {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('security.audits.store'), [
            'manual' => [
                'unique_password' => false,
                'recovery_codes_stored' => false,
                'sessions_reviewed' => true,
                'device_protected' => false,
                'phishing_ready' => true,
            ],
        ])
        ->assertSessionHasNoErrors();

    $audit = SecurityAudit::query()->whereBelongsTo($user)->sole();
    $checks = collect($audit->checks)->keyBy('key');

    expect($audit->risk_level)->toBe('high_risk')
        ->and($audit->failed_count)->toBeGreaterThanOrEqual(4)
        ->and($checks->get('two_factor_enabled')['status'])->toBe('failed')
        ->and($checks->get('unique_password')['status'])->toBe('failed')
        ->and($checks->get('sessions_reviewed')['status'])->toBe('passed');
});

test('security audit checklist input is validated', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from(route('security.edit'))
        ->post(route('security.audits.store'), [
            'manual' => [
                'unique_password' => 'yes',
            ],
        ])
        ->assertSessionHasErrors([
            'manual.unique_password',
            'manual.recovery_codes_stored',
            'manual.sessions_reviewed',
            'manual.device_protected',
            'manual.phishing_ready',
        ])
        ->assertRedirect(route('security.edit'));

    expect(SecurityAudit::query()->whereBelongsTo($user)->exists())->toBeFalse();
});

test('security audit history is private to the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    SecurityAudit::factory()->for($otherUser)->create([
        'score' => 12,
        'checked_at' => now()->addMinute(),
    ]);
    SecurityAudit::factory()->for($user)->create([
        'score' => 74,
        'risk_level' => 'attention',
        'checked_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('securityAudit.latest.score', 74)
            ->has('securityAudit.latest.checks', 1)
            ->has('securityAudit.history', 1)
            ->where('securityAudit.history.0.score', 74),
        );
});

test('security audit refuses a concurrent run for the same user', function () {
    $user = User::factory()->create();
    $lock = Cache::lock('security-audit:user:'.$user->id, 10);

    expect($lock->get())->toBeTrue();

    try {
        $this
            ->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->from(route('security.edit'))
            ->post(route('security.audits.store'), [
                'manual' => [
                    'unique_password' => true,
                    'recovery_codes_stored' => true,
                    'sessions_reviewed' => true,
                    'device_protected' => true,
                    'phishing_ready' => true,
                ],
            ])
            ->assertSessionHasErrors('audit')
            ->assertRedirect(route('security.edit'));
    } finally {
        $lock->release();
    }

    expect(SecurityAudit::query()->whereBelongsTo($user)->exists())->toBeFalse();
});

test('security audit route requires recent password confirmation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('security.audits.store'), [
            'manual' => [
                'unique_password' => true,
                'recovery_codes_stored' => true,
                'sessions_reviewed' => true,
                'device_protected' => true,
                'phishing_ready' => true,
            ],
        ])
        ->assertRedirect(route('password.confirm'));

    expect(SecurityAudit::query()->whereBelongsTo($user)->exists())->toBeFalse();
});

test('security audit user interface contains the score checklist and history', function () {
    $securityPage = file_get_contents(resource_path('js/pages/settings/Security.vue'));
    $auditPanel = file_get_contents(resource_path('js/components/SecurityAuditPanel.vue'));

    expect($securityPage)->toContain('<SecurityAuditPanel :audit="props.securityAudit" />')
        ->and($auditPanel)->toContain('run-security-audit')
        ->and($auditPanel)->toContain('automaticCheckKeys')
        ->and($auditPanel)->toContain('manualKeys')
        ->and($auditPanel)->toContain('audit.history.length')
        ->and($auditPanel)->toContain('scoreRingStyle')
        ->and($auditPanel)->toContain('SecurityController.storeAudit.url()');
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
        ->and($settingsNavigation)->toContain("key: 'settings.one-c'")
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
