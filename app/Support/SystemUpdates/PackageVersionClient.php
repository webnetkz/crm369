<?php

namespace App\Support\SystemUpdates;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class PackageVersionClient
{
    /**
     * @return array{laravel: string|null, composer: string|null}
     */
    public function latest(): array
    {
        return [
            'laravel' => $this->latestLaravelVersion(),
            'composer' => $this->latestComposerVersion(),
        ];
    }

    private function latestLaravelVersion(): ?string
    {
        $response = $this->request()
            ->get('https://repo.packagist.org/p2/laravel/framework.json')
            ->throw();
        $currentMajor = explode('.', app()->version())[0];

        foreach ($response->json('packages.laravel/framework', []) as $package) {
            $version = ltrim((string) ($package['version'] ?? ''), 'v');

            if (
                preg_match('/^\d+\.\d+\.\d+$/', $version) === 1
                && str_starts_with($version, $currentMajor.'.')
            ) {
                return $version;
            }
        }

        return null;
    }

    private function latestComposerVersion(): ?string
    {
        $versions = $this->request()
            ->get('https://getcomposer.org/versions')
            ->throw()
            ->json('stable', []);

        foreach ($versions as $version) {
            $number = (string) ($version['version'] ?? '');

            if (preg_match('/^\d+\.\d+\.\d+$/', $number) === 1) {
                return $number;
            }
        }

        return null;
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->withHeaders(['User-Agent' => 'CRM369-System-Updater'])
            ->connectTimeout((int) config('system-updates.connect_timeout_seconds', 3))
            ->timeout((int) config('system-updates.timeout_seconds', 10))
            ->retry([200, 500], throw: false);
    }
}
