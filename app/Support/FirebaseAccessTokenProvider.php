<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebaseAccessTokenProvider
{
    private const string TOKEN_URI = 'https://oauth2.googleapis.com/token';

    private const string SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function isConfigured(): bool
    {
        $path = $this->serviceAccountPath();

        return $path !== null && is_file($path);
    }

    public function projectId(): string
    {
        $configuredProjectId = trim((string) config('services.fcm.project_id'));

        if ($configuredProjectId !== '') {
            return $configuredProjectId;
        }

        $projectId = $this->credentials()['project_id'] ?? null;

        if (! is_string($projectId) || trim($projectId) === '') {
            throw new RuntimeException('The Firebase project ID is not configured.');
        }

        return trim($projectId);
    }

    public function accessToken(): string
    {
        $cacheKey = 'fcm:oauth-access-token:'.hash('sha256', $this->projectId());
        $cachedToken = Cache::get($cacheKey);

        if (is_string($cachedToken) && $cachedToken !== '') {
            return $cachedToken;
        }

        $credentials = $this->credentials();
        $clientEmail = $credentials['client_email'] ?? null;
        $privateKey = $credentials['private_key'] ?? null;

        if (! is_string($clientEmail) || ! is_string($privateKey)) {
            throw new RuntimeException('The Firebase service account is missing client_email or private_key.');
        }

        $issuedAt = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URI,
            'iat' => $issuedAt,
            'exp' => $issuedAt + 3600,
        ], JSON_THROW_ON_ERROR));
        $unsignedToken = $header.'.'.$claims;

        if (! openssl_sign($unsignedToken, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign the Firebase service account assertion.');
        }

        $response = Http::asForm()
            ->connectTimeout((int) config('services.fcm.connect_timeout_seconds', 5))
            ->timeout((int) config('services.fcm.timeout_seconds', 10))
            ->retry(2, 250)
            ->post(self::TOKEN_URI, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsignedToken.'.'.$this->base64UrlEncode($signature),
            ])
            ->throw();

        $accessToken = $response->json('access_token');
        $expiresIn = max(120, (int) $response->json('expires_in', 3600));

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Firebase OAuth response did not contain an access token.');
        }

        Cache::put($cacheKey, $accessToken, now()->addSeconds($expiresIn - 60));

        return $accessToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        $path = $this->serviceAccountPath();

        if ($path === null || ! is_file($path)) {
            throw new RuntimeException('The Firebase service account file is not configured or does not exist.');
        }

        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw new RuntimeException('Unable to read the Firebase service account file.');
        }

        $credentials = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($credentials)) {
            throw new RuntimeException('The Firebase service account file is invalid.');
        }

        return $credentials;
    }

    private function serviceAccountPath(): ?string
    {
        $configuredPath = trim((string) config('services.fcm.service_account_path'));

        if ($configuredPath === '') {
            return null;
        }

        return str_starts_with($configuredPath, DIRECTORY_SEPARATOR)
            ? $configuredPath
            : base_path($configuredPath);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
