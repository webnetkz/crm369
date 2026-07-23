<?php

use App\Models\MobileAccessToken;
use App\Models\MobileDevice;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Support\Facades\DB;

function mobileLoginPayload(User $user, array $overrides = []): array
{
    return [
        'email' => $user->email,
        'password' => 'password',
        'device_id' => 'android-test-device',
        'device_name' => 'Pixel Test',
        'app_version' => '2.0.0-test',
        ...$overrides,
    ];
}

test('a regular verified user can create and reuse a persistent mobile session', function () {
    $user = User::factory()->create();

    $response = $this->postJson(route('mobile.v1.login'), mobileLoginPayload($user))
        ->assertSuccessful()
        ->assertJsonPath('two_factor_required', false)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.email', $user->email);

    $plainTextToken = $response->json('data.token');
    $mobileAccessToken = MobileAccessToken::query()->firstOrFail();
    $expiresAt = $mobileAccessToken->expires_at;

    expect($plainTextToken)->toStartWith('crm369_mobile_')
        ->and(MobileAccessToken::query()->whereBelongsTo($user)->count())->toBe(1)
        ->and($mobileAccessToken->token_hash)->not->toBe($plainTextToken);

    $this->withToken($plainTextToken)
        ->getJson(route('mobile.v1.me'))
        ->assertSuccessful()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email);

    expect($mobileAccessToken->refresh()->last_used_at)->not->toBeNull()
        ->and($mobileAccessToken->expires_at?->equalTo($expiresAt))->toBeTrue();

    $this->withToken($plainTextToken)
        ->getJson(route('mobile.v1.profile.show'))
        ->assertSuccessful()
        ->assertJsonPath('data.email', $user->email);
});

test('invalid inactive and unverified users cannot create mobile sessions', function () {
    $user = User::factory()->create();

    $this->postJson(route('mobile.v1.login'), mobileLoginPayload($user, ['password' => 'wrong']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $inactiveUser = User::factory()->create(['is_active' => false]);

    $this->postJson(route('mobile.v1.login'), mobileLoginPayload($inactiveUser, ['device_id' => 'inactive-device']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $unverifiedUser = User::factory()->unverified()->create();

    $this->postJson(route('mobile.v1.login'), mobileLoginPayload($unverifiedUser, ['device_id' => 'unverified-device']))
        ->assertForbidden()
        ->assertJsonPath('code', 'email_not_verified');
});

test('mobile login supports a headless two factor recovery challenge', function () {
    $user = User::factory()->withTwoFactor()->create();

    $loginResponse = $this->postJson(route('mobile.v1.login'), mobileLoginPayload($user))
        ->assertSuccessful()
        ->assertJsonPath('two_factor_required', true)
        ->assertJsonMissingPath('data.token');

    $challenge = $loginResponse->json('challenge');

    $response = $this->postJson(route('mobile.v1.two-factor.challenge'), [
        'challenge' => $challenge,
        'recovery_code' => 'recovery-code-1',
    ])->assertSuccessful()
        ->assertJsonPath('two_factor_required', false)
        ->assertJsonPath('data.user.id', $user->id);

    expect($response->json('data.token'))->toStartWith('crm369_mobile_')
        ->and($user->fresh()->recoveryCodes())->not->toContain('recovery-code-1');

    $this->postJson(route('mobile.v1.two-factor.challenge'), [
        'challenge' => $challenge,
        'code' => '123456',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('challenge');
});

test('the current device can register an encrypted FCM token and logout revokes access', function () {
    $user = User::factory()->create();
    $plainTextToken = $this->postJson(route('mobile.v1.login'), mobileLoginPayload($user))
        ->json('data.token');
    $fcmToken = 'fcm-token-'.str_repeat('a', 80);

    $this->withToken($plainTextToken)
        ->putJson(route('mobile.v1.device.store'), [
            'device_id' => 'android-test-device',
            'device_name' => 'Pixel Test',
            'app_version' => '2.0.0-test',
            'fcm_token' => $fcmToken,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.device_id', 'android-test-device');

    $device = MobileDevice::query()->whereBelongsTo($user)->firstOrFail();
    $rawStoredToken = DB::table('mobile_devices')->where('id', $device->id)->value('fcm_token');

    expect($device->fcm_token)->toBe($fcmToken)
        ->and($rawStoredToken)->not->toBe($fcmToken)
        ->and($device->fcm_token_hash)->toBe(hash('sha256', $fcmToken));

    $this->withToken($plainTextToken)
        ->deleteJson(route('mobile.v1.logout'))
        ->assertSuccessful();

    expect(MobileAccessToken::query()->whereBelongsTo($user)->exists())->toBeFalse()
        ->and($device->fresh()->disabled_at)->not->toBeNull();

    $this->withToken($plainTextToken)
        ->getJson(route('mobile.v1.me'))
        ->assertUnauthorized();
});

test('a mobile token cannot authenticate against the administrative integration API', function () {
    $user = User::factory()->create();
    $plainTextToken = $this->postJson(route('mobile.v1.login'), mobileLoginPayload($user))
        ->json('data.token');

    $this->withToken($plainTextToken)
        ->getJson(route('api.v1.profile.show'))
        ->assertUnauthorized();
});

test('mobile module endpoints enforce user group access permissions', function () {
    $restrictedModules = [
        'chats',
        'calendar',
        'company-structure',
        'contacts',
        'knowledge-bases',
        'projects',
        'warehouses',
        'equipment',
        'tsd',
    ];
    $group = UserGroup::factory()->create([
        'permissions' => UserGroup::normalizePermissionsWithConfiguredModules([], $restrictedModules),
    ]);
    $user = User::factory()->create(['user_group_id' => $group->id]);
    $plainTextToken = $this->postJson(route('mobile.v1.login'), mobileLoginPayload($user))
        ->json('data.token');

    foreach ([
        'mobile.v1.chats.index',
        'mobile.v1.calendar.events',
        'mobile.v1.company-structure.index',
        'mobile.v1.contacts.index',
        'mobile.v1.knowledge-bases.index',
        'mobile.v1.projects.index',
        'mobile.v1.warehouses.index',
        'mobile.v1.equipment.index',
        'mobile.v1.tsd.index',
    ] as $routeName) {
        $this->withToken($plainTextToken)
            ->getJson(route($routeName))
            ->assertForbidden();
    }
});
