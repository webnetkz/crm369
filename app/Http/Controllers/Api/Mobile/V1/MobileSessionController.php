<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\LoginRequest;
use App\Http\Requests\Mobile\TwoFactorChallengeRequest;
use App\Http\Resources\ApiUserResource;
use App\Models\MobileAccessToken;
use App\Models\SystemSecuritySetting;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class MobileSessionController extends Controller
{
    public function store(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->email())->first();

        if (! $user || ! $user->is_active || ! Hash::check($request->password(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if ($user->email_verified_at === null) {
            return response()->json([
                'message' => __('Your email address is not verified.'),
                'code' => 'email_not_verified',
            ], 403);
        }

        if (
            SystemSecuritySetting::requiresTwoFactorAuthentication()
            && ! $user->hasEnabledTwoFactorAuthentication()
        ) {
            return response()->json([
                'message' => __('Two-factor authentication must be configured before using the mobile app.'),
                'code' => 'two_factor_setup_required',
            ], 403);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $challenge = Str::random(80);

            Cache::put($this->challengeCacheKey($challenge), [
                'user_id' => $user->id,
                ...$request->deviceContext(),
            ], now()->addMinutes(5));

            return response()->json([
                'two_factor_required' => true,
                'challenge' => $challenge,
                'expires_in' => 300,
            ]);
        }

        return $this->issueSession($user, $request->deviceContext());
    }

    public function challenge(TwoFactorChallengeRequest $request): JsonResponse
    {
        $challengeData = Cache::get($this->challengeCacheKey($request->challenge()));

        if (! is_array($challengeData) || ! isset($challengeData['user_id'], $challengeData['device_id'])) {
            throw ValidationException::withMessages([
                'challenge' => [__('The authentication challenge has expired. Please sign in again.')],
            ]);
        }

        $user = User::query()->whereKey($challengeData['user_id'])->first();

        if (! $user || ! $user->is_active || $user->email_verified_at === null) {
            abort(401);
        }

        if (! $this->verifyTwoFactorCode($user, $request)) {
            throw ValidationException::withMessages([
                'code' => [__('The provided two-factor authentication code was invalid.')],
            ]);
        }

        Cache::forget($this->challengeCacheKey($request->challenge()));

        return $this->issueSession($user, [
            'device_id' => (string) $challengeData['device_id'],
            'device_name' => is_string($challengeData['device_name'] ?? null) ? $challengeData['device_name'] : null,
            'app_version' => is_string($challengeData['app_version'] ?? null) ? $challengeData['app_version'] : null,
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->userResource($request->user()),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $token = $request->attributes->get('mobile_access_token');

        if (! $token instanceof MobileAccessToken) {
            $token = MobileAccessToken::resolve($request->bearerToken());
        }

        abort_unless($token instanceof MobileAccessToken, 401);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($token->user_id === $user->id, 401);

        $user->mobileDevices()
            ->where('device_id', $token->device_id)
            ->update(['disabled_at' => now()]);

        $token->delete();
        Auth::guard('mobile')->forgetUser();

        return response()->json(['message' => __('Signed out.')]);
    }

    /**
     * @param  array{device_id: string, device_name: string|null, app_version: string|null}  $deviceContext
     */
    private function issueSession(User $user, array $deviceContext): JsonResponse
    {
        $issued = MobileAccessToken::issueToUser($user, $deviceContext['device_id']);

        event(new Login('mobile', $user, false));

        return response()->json([
            'two_factor_required' => false,
            'data' => [
                'token' => $issued['plain_text_token'],
                'token_type' => 'Bearer',
                'expires_at' => $issued['mobile_access_token']->expires_at?->toISOString(),
                'user' => $this->userResource($user->fresh('group')),
            ],
        ]);
    }

    private function verifyTwoFactorCode(User $user, TwoFactorChallengeRequest $request): bool
    {
        $code = $request->code();

        if (is_string($code)) {
            return app(TwoFactorAuthenticationProvider::class)->verify(
                Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
                $code,
            );
        }

        $recoveryCode = $request->recoveryCode();

        if (! is_string($recoveryCode) || ! in_array($recoveryCode, $user->recoveryCodes(), true)) {
            return false;
        }

        $user->replaceRecoveryCode($recoveryCode);

        return true;
    }

    private function challengeCacheKey(string $challenge): string
    {
        return 'mobile-login-challenge:'.hash('sha256', $challenge);
    }

    /**
     * @return array<string, mixed>
     */
    private function userResource(?User $user): array
    {
        abort_unless($user instanceof User, 401);

        return (new ApiUserResource($user))->resolve();
    }
}
