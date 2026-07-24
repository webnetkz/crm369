<?php

namespace App\Support\SystemUpdates;

use App\Actions\SystemUpdates\CheckSystemVersions;
use App\Models\SystemUpdateRun;
use App\Models\SystemUpdateSnapshot;

class SystemUpdatePageData
{
    public function __construct(
        private CheckSystemVersions $versions,
        private SystemUpdateProgressReader $progressReader,
        private SystemUpdateDatabaseReadiness $databaseReadiness,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        if (! $this->databaseReadiness->isReady()) {
            return [
                'repository' => $this->repository(),
                'databaseReady' => false,
                'snapshot' => [
                    'components' => $this->versions->localComponents(),
                    'checkedAt' => null,
                    'error' => __('ui.system_updates.errors.database_not_ready'),
                ],
                'latestRun' => null,
                'history' => [],
            ];
        }

        $this->expireStaleRuns();

        $snapshot = SystemUpdateSnapshot::query()->latest('checked_at')->first();
        $latestRun = SystemUpdateRun::query()
            ->with('requestedBy:id,name,last_name')
            ->latest()
            ->first();

        if ($latestRun !== null) {
            $latestRun = $this->syncProgress($latestRun);
        }

        $components = $snapshot?->components ?? $this->versions->localComponents();
        $components = $this->mergeCompletedRun($components, $snapshot, $latestRun);

        return [
            'repository' => $this->repository(),
            'databaseReady' => true,
            'snapshot' => [
                'components' => $components,
                'checkedAt' => $snapshot?->checked_at?->toIso8601String(),
                'error' => $snapshot?->error,
            ],
            'latestRun' => $latestRun === null ? null : $this->run($latestRun, true),
            'history' => SystemUpdateRun::query()
                ->with('requestedBy:id,name,last_name')
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (SystemUpdateRun $run): array => $this->run($run))
                ->all(),
        ];
    }

    /**
     * @return array{name: string, branch: string, url: string}
     */
    private function repository(): array
    {
        $repository = (string) config('system-updates.repository');

        return [
            'name' => $repository,
            'branch' => (string) config('system-updates.branch'),
            'url' => 'https://github.com/'.$repository,
        ];
    }

    private function syncProgress(SystemUpdateRun $run): SystemUpdateRun
    {
        $progress = $this->progressReader->read($run->uuid);

        if ($progress === null) {
            return $run;
        }

        if ($run->isActive()) {
            $updates = [
                'status' => $progress['status'],
                'progress' => $progress['progress'],
                'stage' => $progress['stage'],
                'message' => $progress['message'],
                'started_at' => $progress['started_at'] ?? $run->started_at,
                'finished_at' => $progress['finished_at'],
            ];

            if (
                $run->status !== $updates['status']
                || $run->progress !== $updates['progress']
                || $run->stage !== $updates['stage']
                || $run->message !== $updates['message']
            ) {
                $run->update($updates);
                $run->refresh()->load('requestedBy:id,name,last_name');
            }
        }

        $run->setAttribute('progress_steps', $progress['steps']);

        return $run;
    }

    private function expireStaleRuns(): void
    {
        SystemUpdateRun::query()
            ->whereIn('status', ['queued', 'running'])
            ->where('created_at', '<', now()->subMinutes((int) config('system-updates.run_timeout_minutes', 60)))
            ->update([
                'status' => 'failed',
                'message' => __('ui.system_updates.errors.stale_run'),
                'finished_at' => now(),
            ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @return array<int, array<string, mixed>>
     */
    private function mergeCompletedRun(
        array $components,
        ?SystemUpdateSnapshot $snapshot,
        ?SystemUpdateRun $run,
    ): array {
        if (
            $run === null
            || $run->status !== 'completed'
            || $run->finished_at === null
            || ($snapshot?->checked_at !== null && $snapshot->checked_at->greaterThanOrEqualTo($run->finished_at))
        ) {
            return $components;
        }

        return collect($components)
            ->map(function (array $component) use ($run): array {
                if (($component['key'] ?? null) !== $run->component) {
                    return $component;
                }

                return [
                    ...$component,
                    'currentVersion' => $run->target_version ?? $component['currentVersion'],
                    'status' => 'current',
                    'updateAvailable' => false,
                    'canUpdate' => false,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function run(SystemUpdateRun $run, bool $includeSteps = false): array
    {
        return [
            'id' => $run->id,
            'uuid' => $run->uuid,
            'component' => $run->component,
            'status' => $run->status,
            'currentVersion' => $run->current_version,
            'targetVersion' => $run->target_version,
            'progress' => $run->progress,
            'stage' => $run->stage,
            'message' => $run->message,
            'startedAt' => $run->started_at?->toIso8601String(),
            'finishedAt' => $run->finished_at?->toIso8601String(),
            'createdAt' => $run->created_at?->toIso8601String(),
            'requestedBy' => $run->requestedBy === null ? null : [
                'id' => $run->requestedBy->id,
                'name' => trim($run->requestedBy->name.' '.($run->requestedBy->last_name ?? '')),
            ],
            'steps' => $includeSteps
                ? ($run->getAttribute('progress_steps') ?? [])
                : [],
        ];
    }
}
