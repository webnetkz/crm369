<?php

namespace App\Actions\SystemUpdates;

use App\Models\SystemUpdateRun;
use App\Models\SystemUpdateSnapshot;
use App\Models\User;
use App\Support\SystemUpdates\SystemUpdateDatabaseReadiness;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StartSystemUpdate
{
    public const array COMPONENTS = [
        'application',
        'laravel',
        'php',
        'postgresql',
        'redis',
        'nginx',
        'node',
        'composer',
        'ubuntu',
    ];

    public function __construct(
        private SystemUpdateDatabaseReadiness $databaseReadiness,
    ) {}

    public function execute(User $actor, string $component): SystemUpdateRun
    {
        if (! $this->databaseReadiness->isReady()) {
            throw ValidationException::withMessages([
                'database' => __('ui.system_updates.errors.database_not_ready'),
            ]);
        }

        $lock = Cache::lock('system-updates:start', 15);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'component' => __('ui.system_updates.errors.already_running'),
            ]);
        }

        try {
            return $this->start($actor, $component);
        } finally {
            $lock->release();
        }
    }

    private function start(User $actor, string $component): SystemUpdateRun
    {
        $this->expireStaleRuns();

        if (SystemUpdateRun::query()->whereIn('status', ['queued', 'running'])->exists()) {
            throw ValidationException::withMessages([
                'component' => __('ui.system_updates.errors.already_running'),
            ]);
        }

        $snapshot = SystemUpdateSnapshot::query()->latest('checked_at')->first();
        $componentState = collect($snapshot?->components ?? [])->firstWhere('key', $component);

        if (! is_array($componentState) || ($componentState['updateAvailable'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'component' => __('ui.system_updates.errors.no_update'),
            ]);
        }

        if (($componentState['canUpdate'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'component' => __('ui.system_updates.errors.update_unavailable'),
            ]);
        }

        $targetReference = $component === 'application'
            ? ($componentState['latestReference'] ?? null)
            : ($componentState['latestVersion'] ?? null);

        if ($component === 'application' && (
            ! is_string($targetReference)
            || preg_match('/\A[0-9a-f]{40}\z/', $targetReference) !== 1
        )) {
            throw ValidationException::withMessages([
                'component' => __('ui.system_updates.errors.invalid_release'),
            ]);
        }

        $run = SystemUpdateRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'requested_by_user_id' => $actor->id,
            'component' => $component,
            'status' => 'queued',
            'current_version' => $componentState['currentVersion'] ?? null,
            'target_version' => $componentState['latestVersion'] ?? null,
            'target_reference' => is_string($targetReference) ? $targetReference : null,
            'stage' => 'queued',
            'message' => __('ui.system_updates.run_queued'),
        ]);

        $command = [
            'sudo',
            '-n',
            (string) config('system-updates.bridge_path'),
            'start',
            $run->uuid,
            $component,
        ];

        if (is_string($targetReference) && $targetReference !== '') {
            $command[] = $targetReference;
        }

        if (
            $component === 'application'
            && is_string($componentState['latestVersion'] ?? null)
            && preg_match('/\A[A-Za-z0-9._-]{1,64}\z/', $componentState['latestVersion']) === 1
        ) {
            $command[] = $componentState['latestVersion'];
        }

        try {
            $result = Process::timeout(15)->run($command);
        } catch (Throwable $exception) {
            $this->failRun($run, $exception->getMessage());
            throw ValidationException::withMessages([
                'component' => __('ui.system_updates.errors.cannot_start'),
            ]);
        }

        if (! $result->successful()) {
            $this->failRun($run, trim($result->errorOutput()) ?: trim($result->output()));

            throw ValidationException::withMessages([
                'component' => __('ui.system_updates.errors.cannot_start'),
            ]);
        }

        $run->update([
            'status' => 'running',
            'progress' => 1,
            'stage' => 'starting',
            'message' => __('ui.system_updates.run_started'),
            'started_at' => now(),
        ]);

        return $run->fresh();
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

    private function failRun(SystemUpdateRun $run, string $message): void
    {
        $run->update([
            'status' => 'failed',
            'message' => mb_substr($message, 0, 1000),
            'finished_at' => now(),
        ]);
    }
}
