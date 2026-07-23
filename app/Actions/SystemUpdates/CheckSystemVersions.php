<?php

namespace App\Actions\SystemUpdates;

use App\Models\SystemUpdateSnapshot;
use App\Support\SystemUpdates\GitHubReleaseClient;
use App\Support\SystemUpdates\PackageVersionClient;
use App\Support\SystemUpdates\SystemComponentInventory;
use App\Support\SystemUpdates\SystemUpdateDatabaseReadiness;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use Throwable;

class CheckSystemVersions
{
    public function __construct(
        private GitHubReleaseClient $github,
        private PackageVersionClient $packages,
        private SystemComponentInventory $inventory,
        private SystemUpdateDatabaseReadiness $databaseReadiness,
    ) {}

    public function execute(): SystemUpdateSnapshot
    {
        if (! $this->databaseReadiness->isReady()) {
            throw ValidationException::withMessages([
                'database' => __('ui.system_updates.errors.database_not_ready'),
            ]);
        }

        $errors = [];
        $latestApplication = null;
        $latestPackages = ['laravel' => null, 'composer' => null];

        try {
            $latestApplication = $this->github->latest();
        } catch (Throwable $exception) {
            report($exception);
            $errors[] = __('ui.system_updates.errors.github_unavailable');
        }

        try {
            $latestPackages = $this->packages->latest();
        } catch (Throwable $exception) {
            report($exception);
            $errors[] = __('ui.system_updates.errors.package_sources_unavailable');
        }

        return SystemUpdateSnapshot::query()->create([
            'components' => [
                $this->applicationComponent($latestApplication),
                ...$this->inventory->collect($latestPackages),
            ],
            'error' => $errors === [] ? null : implode(' ', $errors),
            'checked_at' => now(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function localComponents(): array
    {
        return [
            $this->applicationComponent(null),
            ...$this->inventory->collect(['laravel' => null, 'composer' => null]),
        ];
    }

    /**
     * @param  array{
     *     version: string,
     *     reference: string,
     *     release_url: string,
     *     published_at: string|null,
     *     channel: string
     * }|null  $latest
     * @return array<string, mixed>
     */
    private function applicationComponent(?array $latest): array
    {
        $current = $this->currentApplication();
        $currentReference = $current['reference'];
        $latestReference = $latest['reference'] ?? null;
        $updateAvailable = $currentReference !== null
            && $latestReference !== null
            && ! hash_equals($currentReference, $latestReference);
        $bridgeAvailable = $this->inventory->bridgeAvailable();

        return [
            'key' => 'application',
            'currentVersion' => $current['version'],
            'latestVersion' => $latest['version'] ?? null,
            'currentReference' => $currentReference,
            'latestReference' => $latestReference,
            'releaseUrl' => $latest['release_url'] ?? null,
            'publishedAt' => $latest['published_at'] ?? null,
            'channel' => $latest['channel'] ?? null,
            'status' => match (true) {
                $currentReference === null || $latestReference === null => 'unknown',
                $updateAvailable => 'update_available',
                default => 'current',
            },
            'updateAvailable' => $updateAvailable,
            'canUpdate' => $updateAvailable && $bridgeAvailable && ! $current['dirty'],
            'blockedReason' => match (true) {
                $current['dirty'] => 'working_tree_modified',
                ! $bridgeAvailable => 'bridge_unavailable',
                default => null,
            },
            'source' => 'github',
        ];
    }

    /**
     * @return array{version: string|null, reference: string|null, dirty: bool}
     */
    private function currentApplication(): array
    {
        $statePath = (string) config('system-updates.version_state_path');

        if ($statePath !== '' && is_readable($statePath)) {
            $state = json_decode((string) file_get_contents($statePath), true);
            $reference = is_array($state) && is_string($state['reference'] ?? null)
                ? $state['reference']
                : null;

            if ($reference !== null && preg_match('/\A[0-9a-f]{40}\z/', $reference) === 1) {
                return [
                    'version' => is_string($state['version'] ?? null)
                        ? $state['version']
                        : 'commit '.mb_substr($reference, 0, 8),
                    'reference' => $reference,
                    'dirty' => false,
                ];
            }
        }

        try {
            $referenceResult = Process::path(base_path())
                ->timeout(5)
                ->run(['git', 'rev-parse', 'HEAD']);
            $reference = trim($referenceResult->output());

            if (! $referenceResult->successful() || preg_match('/\A[0-9a-f]{40}\z/', $reference) !== 1) {
                return ['version' => null, 'reference' => null, 'dirty' => false];
            }

            $dirtyResult = Process::path(base_path())
                ->timeout(5)
                ->run(['git', 'status', '--porcelain']);

            return [
                'version' => (string) config('system-updates.branch', 'main').' @ '.mb_substr($reference, 0, 8),
                'reference' => $reference,
                'dirty' => $dirtyResult->successful() && trim($dirtyResult->output()) !== '',
            ];
        } catch (Throwable) {
            return ['version' => null, 'reference' => null, 'dirty' => false];
        }
    }
}
