<?php

use App\Models\ApiAccessToken;
use App\Models\SystemSecurityAudit;
use App\Models\SystemSecuritySetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(function () {
    config(['admin.super_admin_email' => 'super@example.com']);
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);
    SystemSecuritySetting::forgetCachedRequirement();
});

test('system security page is restricted to a password confirmed super administrator', function () {
    $user = User::factory()->create();
    $superAdmin = User::factory()->withTwoFactor()->create([
        'email' => 'super@example.com',
    ]);

    $this->get(route('settings.system-security.edit'))
        ->assertRedirect(route('login'));

    $this->actingAs($superAdmin)
        ->get(route('settings.system-security.edit'))
        ->assertRedirect(route('password.confirm'));

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.system-security.edit'))
        ->assertForbidden();
});

test('super administrator sees system security posture and two factor coverage', function () {
    $superAdmin = User::factory()->withTwoFactor()->create([
        'email' => 'super@example.com',
    ]);
    User::factory()->create();

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.system-security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/SystemSecurity')
            ->where('policy.enabled', false)
            ->where('policy.featureAvailable', true)
            ->where('policy.activeUsers', 2)
            ->where('policy.protectedUsers', 1)
            ->where('policy.pendingUsers', 1)
            ->where('policy.coveragePercent', 50)
            ->where('audit.latest', null)
            ->where('audit.history', [])
            ->where('audit.manualDefaults.backups_verified', false),
        );
});

test('mandatory two factor policy cannot be enabled before the super administrator configures two factor', function () {
    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from(route('settings.system-security.edit'))
        ->patch(route('settings.system-security.two-factor-requirement.update'), [
            'enabled' => true,
        ])
        ->assertRedirect(route('settings.system-security.edit'))
        ->assertSessionHasErrors('enabled');

    expect(SystemSecuritySetting::requiresTwoFactorAuthentication())->toBeFalse();
});

test('mandatory two factor policy cannot be enabled when Fortify two factor is disabled', function () {
    config(['fortify.features' => []]);

    $superAdmin = User::factory()->withTwoFactor()->create([
        'email' => 'super@example.com',
    ]);

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from(route('settings.system-security.edit'))
        ->patch(route('settings.system-security.two-factor-requirement.update'), [
            'enabled' => true,
        ])
        ->assertSessionHasErrors('enabled');

    expect(SystemSecuritySetting::requiresTwoFactorAuthentication())->toBeFalse();
});

test('super administrator retains a break glass path to disable the policy if Fortify two factor becomes unavailable', function () {
    config(['fortify.features' => []]);

    SystemSecuritySetting::factory()->create([
        'requires_two_factor_authentication' => true,
        'enforced_at' => now(),
    ]);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.system-security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('policy.enabled', true)
            ->where('policy.featureAvailable', false),
        );

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->patch(route('settings.system-security.two-factor-requirement.update'), [
            'enabled' => false,
        ])
        ->assertSessionHasNoErrors();

    expect(SystemSecuritySetting::requiresTwoFactorAuthentication())->toBeFalse();
});

test('super administrator can enable and disable mandatory two factor for everyone', function () {
    $superAdmin = User::factory()->withTwoFactor()->create([
        'email' => 'super@example.com',
    ]);

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from(route('settings.system-security.edit'))
        ->patch(route('settings.system-security.two-factor-requirement.update'), [
            'enabled' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.system-security.edit'));

    $setting = SystemSecuritySetting::query()->sole();

    expect($setting->requires_two_factor_authentication)->toBeTrue()
        ->and($setting->enforced_at)->not->toBeNull()
        ->and($setting->updated_by_user_id)->toBe($superAdmin->id)
        ->and(SystemSecuritySetting::requiresTwoFactorAuthentication())->toBeTrue();

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->patch(route('settings.system-security.two-factor-requirement.update'), [
            'enabled' => false,
        ])
        ->assertSessionHasNoErrors();

    expect($setting->fresh()->requires_two_factor_authentication)->toBeFalse()
        ->and($setting->fresh()->enforced_at)->toBeNull()
        ->and(SystemSecuritySetting::requiresTwoFactorAuthentication())->toBeFalse();
});

test('users without two factor are redirected to setup while protected users continue', function () {
    SystemSecuritySetting::factory()->create([
        'requires_two_factor_authentication' => true,
        'enforced_at' => now(),
    ]);

    $unprotectedUser = User::factory()->create();
    $protectedUser = User::factory()->withTwoFactor()->create();

    $this->actingAs($unprotectedUser)
        ->get(route('dashboard'))
        ->assertRedirect(route('security.edit'));

    $this->actingAs($unprotectedUser)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('twoFactorRequired', true)
            ->where('mustCompleteTwoFactor', true),
        );

    $this->actingAs($protectedUser)
        ->get(route('dashboard'))
        ->assertOk();
});

test('two factor cannot be disabled while the mandatory policy is active', function () {
    SystemSecuritySetting::factory()->create([
        'requires_two_factor_authentication' => true,
        'enforced_at' => now(),
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('two-factor.disable'))
        ->assertRedirect(route('security.edit'))
        ->assertSessionHasErrors('two_factor');

    expect($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

test('mandatory two factor policy blocks api tokens until their owner enables two factor', function () {
    SystemSecuritySetting::factory()->create([
        'requires_two_factor_authentication' => true,
        'enforced_at' => now(),
    ]);

    $user = User::factory()->create([
        'email' => 'super@example.com',
    ]);
    $issued = ApiAccessToken::issueToUser(
        $user,
        'Security policy test',
        [ApiAccessToken::PERMISSION_PROFILE_READ],
        Carbon::now()->addDay(),
    );

    $this->withToken($issued['plain_text_token'])
        ->getJson(route('api.v1.profile.show'))
        ->assertUnauthorized();

    $user->forceFill([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->withToken($issued['plain_text_token'])
        ->getJson(route('api.v1.profile.show'))
        ->assertOk();
});

test('super administrator can run a redacted system security audit', function () {
    Process::fake([
        '*' => Process::result(output: json_encode([
            'advisories' => [],
            'abandoned' => [],
            'metadata' => [
                'vulnerabilities' => [
                    'total' => 0,
                    'high' => 0,
                    'critical' => 0,
                ],
            ],
        ], JSON_THROW_ON_ERROR)),
    ]);

    $superAdmin = User::factory()->withTwoFactor()->create([
        'email' => 'super@example.com',
    ]);

    $response = $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from(route('settings.system-security.edit'))
        ->post(route('settings.system-security.audits.store'), [
            'manual' => [
                'backups_verified' => true,
                'infrastructure_patched' => true,
                'privileged_access_reviewed' => true,
                'security_headers_configured' => true,
                'incident_plan_ready' => true,
                'secrets_rotated' => true,
            ],
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.system-security.edit'));

    $audit = SystemSecurityAudit::query()->sole();
    $checks = collect($audit->checks)->keyBy('key');

    expect($audit->performed_by_user_id)->toBe($superAdmin->id)
        ->and($audit->total_count)->toBe(28)
        ->and($audit->duration_ms)->toBeGreaterThan(0)
        ->and($checks)->toHaveKeys([
            'debug_mode',
            'email_verification',
            'two_factor_coverage',
            'api_tokens',
            'webhook_tokens',
            'pending_migrations',
            'integration_tls',
            'integration_secrets',
            'composer_dependencies',
            'npm_dependencies',
            'backups_verified',
            'incident_plan_ready',
        ])
        ->and(json_encode($audit->checks, JSON_THROW_ON_ERROR))
        ->not->toContain('super@example.com')
        ->not->toContain('recovery-code')
        ->not->toContain('APP_KEY');

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.system-security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('audit.latest.id', $audit->id)
            ->where('audit.latest.totalCount', 28)
            ->has('audit.latest.checks', 28)
            ->has('audit.history', 1),
        );
});

test('system security audit validates every manual checklist item', function () {
    $superAdmin = User::factory()->withTwoFactor()->create([
        'email' => 'super@example.com',
    ]);

    $this->actingAs($superAdmin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('settings.system-security.audits.store'), [
            'manual' => [],
        ])
        ->assertSessionHasErrors([
            'manual.backups_verified',
            'manual.infrastructure_patched',
            'manual.privileged_access_reviewed',
            'manual.security_headers_configured',
            'manual.incident_plan_ready',
            'manual.secrets_rotated',
        ]);

    expect(SystemSecurityAudit::query()->exists())->toBeFalse();
});

test('system security page exposes the audit and mandatory two factor controls', function () {
    $page = file_get_contents(resource_path('js/pages/settings/SystemSecurity.vue'));
    $navigation = file_get_contents(resource_path('js/composables/useSettingsNavigation.ts'));

    expect($page)
        ->toContain('data-test="run-system-security-audit"')
        ->toContain('data-test="toggle-mandatory-two-factor"')
        ->toContain('automaticCheckKeys')
        ->toContain('manualKeys')
        ->toContain('findings')
        ->and($navigation)->toContain("key: 'settings.system-security'");
});
