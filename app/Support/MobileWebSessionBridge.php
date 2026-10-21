<?php

namespace App\Support;

use App\Models\MobileAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Exchanges an authenticated native mobile session for a short-lived,
 * single-use link that signs the same user into the web application inside
 * the in-app browser (administration pages and conference rooms).
 */
class MobileWebSessionBridge
{
    public const int TTL_SECONDS = 60;

    private const string CACHE_PREFIX = 'mobile-web-session:';

    /**
     * @return array{token: string, expires_in: int}
     */
    public function issue(User $user, MobileAccessToken $mobileAccessToken, string $path): array
    {
        $token = Str::random(64);

        Cache::put($this->cacheKey($token), [
            'user_id' => $user->id,
            'mobile_access_token_id' => $mobileAccessToken->id,
            'path' => $path,
        ], now()->addSeconds(self::TTL_SECONDS));

        return [
            'token' => $token,
            'expires_in' => self::TTL_SECONDS,
        ];
    }

    /**
     * Consume a link token. A token can be used only once.
     *
     * @return array{user: User, path: string}|null
     */
    public function consume(string $token): ?array
    {
        if (strlen($token) !== 64) {
            return null;
        }

        $payload = Cache::pull($this->cacheKey($token));

        if (! is_array($payload) || ! is_numeric($payload['user_id'] ?? null) || ! is_numeric($payload['mobile_access_token_id'] ?? null)) {
            return null;
        }

        $mobileAccessToken = MobileAccessToken::query()->find((int) $payload['mobile_access_token_id']);

        if (
            ! $mobileAccessToken instanceof MobileAccessToken
            || ! $mobileAccessToken->isAvailable()
            || $mobileAccessToken->user_id !== (int) $payload['user_id']
        ) {
            return null;
        }

        $user = User::query()->find((int) $payload['user_id']);

        if (! $user instanceof User || ! $user->is_active || $user->email_verified_at === null) {
            return null;
        }

        return [
            'user' => $user,
            'path' => self::normalizePath(is_string($payload['path'] ?? null) ? $payload['path'] : '/dashboard'),
        ];
    }

    /**
     * Only same-origin relative paths are allowed as redirect targets.
     */
    public static function normalizePath(?string $path): string
    {
        $path = trim((string) $path);

        if (
            $path === ''
            || ! str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || str_contains($path, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
        ) {
            return '/dashboard';
        }

        return $path;
    }

    private function cacheKey(string $token): string
    {
        return self::CACHE_PREFIX.hash('sha256', $token);
    }
}
