<?php

namespace App\Support\SystemUpdates;

class SystemUpdateProgressReader
{
    /**
     * @return array{
     *     status: string,
     *     progress: int,
     *     stage: string|null,
     *     message: string|null,
     *     started_at: string|null,
     *     finished_at: string|null,
     *     steps: array<int, array{at: string|null, progress: int, stage: string|null, message: string|null}>
     * }|null
     */
    public function read(string $uuid): ?array
    {
        if (preg_match('/\A[0-9a-f-]{36}\z/', $uuid) !== 1) {
            return null;
        }

        $directory = rtrim((string) config('system-updates.progress_directory'), '/');
        $statePath = $directory.'/'.$uuid.'.json';

        if (! is_readable($statePath)) {
            return null;
        }

        $state = json_decode((string) file_get_contents($statePath), true);

        if (! is_array($state) || ! in_array($state['status'] ?? null, ['queued', 'running', 'completed', 'failed'], true)) {
            return null;
        }

        return [
            'status' => $state['status'],
            'progress' => max(0, min(100, (int) ($state['progress'] ?? 0))),
            'stage' => $this->text($state['stage'] ?? null, 120),
            'message' => $this->text($state['message'] ?? null, 1000),
            'started_at' => $this->date($state['started_at'] ?? null),
            'finished_at' => $this->date($state['finished_at'] ?? null),
            'steps' => $this->steps($directory.'/'.$uuid.'.steps'),
        ];
    }

    /**
     * @return array<int, array{at: string|null, progress: int, stage: string|null, message: string|null}>
     */
    private function steps(string $path): array
    {
        if (! is_readable($path)) {
            return [];
        }

        $lines = array_slice(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -100);

        return collect($lines)
            ->map(function (string $line): ?array {
                $step = json_decode($line, true);

                if (! is_array($step)) {
                    return null;
                }

                return [
                    'at' => $this->date($step['at'] ?? null),
                    'progress' => max(0, min(100, (int) ($step['progress'] ?? 0))),
                    'stage' => $this->text($step['stage'] ?? null, 120),
                    'message' => $this->text($step['message'] ?? null, 1000),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function text(mixed $value, int $limit): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return mb_substr(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '', 0, $limit);
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}T/', $value) !== 1) {
            return null;
        }

        return mb_substr($value, 0, 40);
    }
}
