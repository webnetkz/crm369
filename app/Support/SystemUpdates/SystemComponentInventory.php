<?php

namespace App\Support\SystemUpdates;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Throwable;

class SystemComponentInventory
{
    /**
     * @param  array{laravel: string|null, composer: string|null}  $remoteVersions
     * @return array<int, array<string, mixed>>
     */
    public function collect(array $remoteVersions): array
    {
        $bridgeAvailable = $this->bridgeAvailable();
        $postgresVersion = $this->postgresVersion();
        $components = [
            $this->runtimeComponent(
                key: 'laravel',
                current: app()->version(),
                latest: $remoteVersions['laravel'],
                bridgeAvailable: $bridgeAvailable,
                source: 'packagist',
            ),
            $this->aptComponent(
                key: 'php',
                package: 'php'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'-cli',
                current: PHP_VERSION,
                bridgeAvailable: $bridgeAvailable,
            ),
            $this->aptComponent(
                key: 'postgresql',
                package: $this->postgresPackage($postgresVersion),
                current: $postgresVersion,
                bridgeAvailable: $bridgeAvailable,
            ),
            $this->aptComponent(
                key: 'redis',
                package: 'redis-server',
                current: $this->commandVersion(['redis-server', '--version'], '/v=([0-9.]+)/'),
                bridgeAvailable: $bridgeAvailable,
            ),
            $this->aptComponent(
                key: 'nginx',
                package: 'nginx',
                current: $this->commandVersion(['nginx', '-v'], '#nginx/([0-9.]+)#'),
                bridgeAvailable: $bridgeAvailable,
            ),
            $this->aptComponent(
                key: 'node',
                package: 'nodejs',
                current: $this->commandVersion(['node', '--version'], '/v?([0-9.]+)/'),
                bridgeAvailable: $bridgeAvailable,
            ),
            $this->runtimeComponent(
                key: 'composer',
                current: $this->commandVersion(['composer', '--version', '--no-ansi'], '/Composer version ([0-9.]+)/'),
                latest: $remoteVersions['composer'],
                bridgeAvailable: $bridgeAvailable,
                source: 'composer',
            ),
            $this->ubuntuComponent($bridgeAvailable),
        ];

        return array_values($components);
    }

    public function bridgeAvailable(): bool
    {
        $path = (string) config('system-updates.bridge_path');

        return $path !== '' && is_file($path) && is_executable($path);
    }

    /**
     * @return array<string, mixed>
     */
    private function runtimeComponent(
        string $key,
        ?string $current,
        ?string $latest,
        bool $bridgeAvailable,
        string $source,
    ): array {
        $updateAvailable = $current !== null
            && $latest !== null
            && version_compare($latest, $current, '>');

        return $this->component(
            key: $key,
            current: $current,
            latest: $latest,
            updateAvailable: $updateAvailable,
            bridgeAvailable: $bridgeAvailable,
            source: $source,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function aptComponent(
        string $key,
        string $package,
        ?string $current,
        bool $bridgeAvailable,
    ): array {
        $policy = $this->aptPolicy($package);
        $installed = $policy['installed'] ?? null;
        $candidate = $policy['candidate'] ?? null;
        $updateAvailable = $installed !== null
            && $candidate !== null
            && $installed !== '(none)'
            && $candidate !== '(none)'
            && $installed !== $candidate;

        return $this->component(
            key: $key,
            current: $current ?? $installed,
            latest: $candidate,
            updateAvailable: $updateAvailable,
            bridgeAvailable: $bridgeAvailable,
            source: 'apt',
            installedPackageVersion: $installed,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function ubuntuComponent(bool $bridgeAvailable): array
    {
        $version = null;

        if (is_readable('/etc/os-release')) {
            $contents = (string) file_get_contents('/etc/os-release');

            if (preg_match('/^PRETTY_NAME="?([^"\n]+)"?/m', $contents, $matches) === 1) {
                $version = $matches[1];
            }
        }

        $result = $this->run(['apt', 'list', '--upgradable']);
        $pending = $result?->successful()
            ? max(0, count(array_filter(
                preg_split('/\R/', trim($result->output())) ?: [],
                fn (string $line): bool => str_contains($line, '/'),
            )))
            : null;

        return [
            ...$this->component(
                key: 'ubuntu',
                current: $version,
                latest: $pending === null ? null : (string) $pending,
                updateAvailable: ($pending ?? 0) > 0,
                bridgeAvailable: $bridgeAvailable,
                source: 'apt',
            ),
            'pendingPackages' => $pending,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function component(
        string $key,
        ?string $current,
        ?string $latest,
        bool $updateAvailable,
        bool $bridgeAvailable,
        string $source,
        ?string $installedPackageVersion = null,
    ): array {
        return [
            'key' => $key,
            'currentVersion' => $current,
            'latestVersion' => $latest,
            'installedPackageVersion' => $installedPackageVersion,
            'status' => match (true) {
                $current === null || $latest === null => 'unknown',
                $updateAvailable => 'update_available',
                default => 'current',
            },
            'updateAvailable' => $updateAvailable,
            'canUpdate' => $updateAvailable && $bridgeAvailable,
            'blockedReason' => $updateAvailable && ! $bridgeAvailable
                ? 'bridge_unavailable'
                : null,
            'source' => $source,
        ];
    }

    /**
     * @return array{installed: string|null, candidate: string|null}
     */
    private function aptPolicy(string $package): array
    {
        $result = $this->run(['apt-cache', 'policy', $package]);

        if (! $result?->successful()) {
            return ['installed' => null, 'candidate' => null];
        }

        preg_match('/^\s*Installed:\s*(\S+)/m', $result->output(), $installed);
        preg_match('/^\s*Candidate:\s*(\S+)/m', $result->output(), $candidate);

        return [
            'installed' => $installed[1] ?? null,
            'candidate' => $candidate[1] ?? null,
        ];
    }

    private function postgresVersion(): ?string
    {
        try {
            $version = DB::scalar('SHOW server_version');

            return is_string($version) ? $version : null;
        } catch (Throwable) {
            return $this->commandVersion(['psql', '--version'], '/psql \([^)]*\) ([0-9.]+)/');
        }
    }

    private function postgresPackage(?string $version): string
    {
        if ($version !== null && preg_match('/\A(\d+)/', $version, $matches) === 1) {
            return 'postgresql-'.$matches[1];
        }

        return 'postgresql';
    }

    /**
     * @param  array<int, string>  $command
     */
    private function commandVersion(array $command, string $pattern): ?string
    {
        $result = $this->run($command);
        $output = ($result?->output() ?? '').($result?->errorOutput() ?? '');

        return preg_match($pattern, $output, $matches) === 1 ? $matches[1] : null;
    }

    /**
     * @param  array<int, string>  $command
     */
    private function run(array $command): ?ProcessResult
    {
        try {
            return Process::timeout(10)->run($command);
        } catch (Throwable) {
            return null;
        }
    }
}
