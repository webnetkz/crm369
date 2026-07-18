<?php

namespace App\Support;

use App\Models\OneCIntegration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class OneCConnectionTester
{
    /**
     * @return array{succeeded: bool, duration_ms: int, message: string}
     */
    public function test(OneCIntegration $integration): array
    {
        $testUrl = $integration->testUrl();

        if ($testUrl === null) {
            return $this->failure(__('ui.one_c.connection.base_url_missing'));
        }

        if (! $integration->hasRequiredCredentials()) {
            return $this->failure(__('ui.one_c.connection.credentials_missing'));
        }

        $startedAt = hrtime(true);

        try {
            $response = $this->request($integration)
                ->retry(3, 200, function (Throwable $exception): bool {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    return $exception instanceof RequestException
                        && ($exception->response->serverError() || $exception->response->status() === 429);
                }, throw: false)
                ->get($testUrl);

            $duration = $this->durationSince($startedAt);

            if ($response->successful()) {
                return [
                    'succeeded' => true,
                    'duration_ms' => $duration,
                    'message' => __('ui.one_c.connection.success', ['status' => $response->status()]),
                ];
            }

            return [
                'succeeded' => false,
                'duration_ms' => $duration,
                'message' => match ($response->status()) {
                    401, 403 => __('ui.one_c.connection.auth_failed', ['status' => $response->status()]),
                    404 => __('ui.one_c.connection.not_found'),
                    default => __('ui.one_c.connection.http_failed', ['status' => $response->status()]),
                },
            ];
        } catch (ConnectionException) {
            return $this->failure(
                __('ui.one_c.connection.unavailable'),
                $this->durationSince($startedAt),
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure(
                __('ui.one_c.connection.unexpected_error'),
                $this->durationSince($startedAt),
            );
        }
    }

    private function request(OneCIntegration $integration): PendingRequest
    {
        $request = Http::withHeaders([
            'Accept' => 'application/xml, application/json',
            'User-Agent' => 'CRM369-1C-Integration/1.0',
        ])
            ->connectTimeout($integration->connect_timeout_seconds)
            ->timeout($integration->request_timeout_seconds)
            ->withOptions([
                'allow_redirects' => false,
                'verify' => $integration->verify_tls,
            ]);

        return match ($integration->auth_type) {
            OneCIntegration::AUTH_BASIC => $request->withBasicAuth(
                (string) $integration->username,
                (string) $integration->password,
            ),
            OneCIntegration::AUTH_BEARER => $request->withToken((string) $integration->token),
            default => $request,
        };
    }

    /**
     * @return array{succeeded: false, duration_ms: int, message: string}
     */
    private function failure(string $message, int $duration = 0): array
    {
        return [
            'succeeded' => false,
            'duration_ms' => $duration,
            'message' => $message,
        ];
    }

    private function durationSince(int $startedAt): int
    {
        return max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000));
    }
}
