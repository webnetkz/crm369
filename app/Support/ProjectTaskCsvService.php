<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskStage;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectTaskCsvService
{
    /**
     * @var array<int, string>
     */
    private const array HEADERS = [
        'task_key',
        'parent_task_key',
        'title',
        'description',
        'status',
        'importance',
        'complexity',
        'due_at',
        'sort_order',
        'assignee_email',
        'co_assignee_emails',
    ];

    public function __construct(
        private readonly TaskConversationManager $taskConversationManager,
    ) {}

    /**
     * @param  Collection<int, ProjectTask>  $tasks
     */
    public function download(Collection $tasks, string $fileName): StreamedResponse
    {
        $tasksByParent = $tasks
            ->sortBy([
                ['sort_order', 'asc'],
                ['due_at', 'asc'],
                ['created_at', 'desc'],
            ])
            ->groupBy(fn (ProjectTask $task): string => (string) ($task->parent_task_id ?? 'root'));

        return response()->streamDownload(function () use ($tasksByParent): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, self::HEADERS);

            foreach ($this->flattenForExport($tasksByParent) as $task) {
                fputcsv($output, [
                    'task_'.$task->id,
                    $task->parent_task_id !== null ? 'task_'.$task->parent_task_id : '',
                    $task->title,
                    $task->description ?? '',
                    $task->status,
                    $task->importance,
                    $task->complexity,
                    $task->due_at?->toISOString() ?? '',
                    $task->sort_order,
                    $task->assignee?->email ?? '',
                    $task->coAssignees
                        ->pluck('email')
                        ->filter(fn (mixed $email): bool => is_string($email) && $email !== '')
                        ->implode('|'),
                ]);
            }

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(UploadedFile $file, User $actor, ?Project $project = null): int
    {
        $rows = $this->parseRows($file, $project);

        return DB::transaction(function () use ($rows, $actor, $project): int {
            $createdTasks = [];

            foreach ($rows as $row) {
                $task = ProjectTask::query()->create([
                    'project_id' => $project?->id,
                    'parent_task_id' => null,
                    'creator_user_id' => $actor->id,
                    'assignee_user_id' => $row['assignee_user_id'],
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'status' => $row['status'],
                    'importance' => $row['importance'],
                    'complexity' => $row['complexity'],
                    'due_at' => $row['due_at'],
                    'due_reminder_sent_at' => null,
                    'completed_at' => ProjectTaskStage::isCompletedSlug($row['status']) ? now() : null,
                    'sort_order' => $row['sort_order'],
                    'updated_by_user_id' => $actor->id,
                ]);

                $task->coAssignees()->sync($row['co_assignee_user_ids']);
                $this->taskConversationManager->ensureForTask($task, $actor);

                $createdTasks[$row['task_key']] = $task;
            }

            foreach ($rows as $row) {
                if ($row['parent_task_key'] === null) {
                    continue;
                }

                $createdTasks[$row['task_key']]->update([
                    'parent_task_id' => $createdTasks[$row['parent_task_key']]->id,
                    'updated_by_user_id' => $actor->id,
                ]);
            }

            return count($createdTasks);
        });
    }

    /**
     * @param  Collection<string, Collection<int, ProjectTask>>  $tasksByParent
     * @return Collection<int, ProjectTask>
     */
    private function flattenForExport(Collection $tasksByParent, ?int $parentTaskId = null): Collection
    {
        $groupKey = (string) ($parentTaskId ?? 'root');
        /** @var Collection<int, ProjectTask> $tasks */
        $tasks = $tasksByParent->get($groupKey, collect());

        return $tasks->flatMap(function (ProjectTask $task) use ($tasksByParent): Collection {
            return collect([$task])->concat($this->flattenForExport($tasksByParent, $task->id));
        })->values();
    }

    /**
     * @return array<int, array{
     *     row: int,
     *     task_key: string,
     *     parent_task_key: string|null,
     *     title: string,
     *     description: string|null,
     *     status: string,
     *     importance: string,
     *     complexity: int,
     *     due_at: CarbonInterface|null,
     *     sort_order: int,
     *     assignee_user_id: int|null,
     *     co_assignee_user_ids: array<int, int>
     * }>
     */
    private function parseRows(UploadedFile $file, ?Project $project = null): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => __('ui.projects.csv_import_invalid_file'),
            ]);
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            throw ValidationException::withMessages([
                'file' => __('ui.projects.csv_import_empty'),
            ]);
        }

        $normalizedHeader = array_map(
            fn (mixed $value): string => $this->normalizeHeaderValue($value),
            $header,
        );

        $missingHeaders = array_values(array_diff(self::HEADERS, $normalizedHeader));

        if ($missingHeaders !== []) {
            fclose($handle);

            throw ValidationException::withMessages([
                'file' => __('ui.projects.csv_import_missing_headers', [
                    'columns' => implode(', ', $missingHeaders),
                ]),
            ]);
        }

        $rows = [];
        $seenTaskKeys = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $mappedRow = [];

            foreach ($normalizedHeader as $index => $column) {
                $mappedRow[$column] = isset($row[$index]) && is_string($row[$index])
                    ? trim($row[$index])
                    : '';
            }

            $taskKey = $mappedRow['task_key'] !== ''
                ? $mappedRow['task_key']
                : 'row-'.$rowNumber;

            if (isset($seenTaskKeys[$taskKey])) {
                fclose($handle);

                throw ValidationException::withMessages([
                    'file' => __('ui.projects.csv_import_duplicate_task_key', ['key' => $taskKey]),
                ]);
            }

            $status = $mappedRow['status'] !== '' ? $mappedRow['status'] : ProjectTask::STATUS_TODO;
            $importance = $mappedRow['importance'] !== '' ? $mappedRow['importance'] : ProjectTask::IMPORTANCE_NORMAL;
            $complexity = $mappedRow['complexity'] !== '' ? (int) $mappedRow['complexity'] : 5;
            $sortOrder = $mappedRow['sort_order'] !== '' ? (int) $mappedRow['sort_order'] : 0;
            $assigneeUserId = $this->resolveAssigneeUserId($mappedRow['assignee_email'], $project, $rowNumber);
            $coAssigneeUserIds = $this->resolveCoAssigneeUserIds(
                $mappedRow['co_assignee_emails'],
                $project,
                $rowNumber,
                $assigneeUserId,
            );

            if ($mappedRow['title'] === '') {
                fclose($handle);

                throw ValidationException::withMessages([
                    'file' => __('ui.projects.csv_import_row_error', [
                        'row' => $rowNumber,
                        'message' => __('validation.required', ['attribute' => 'title']),
                    ]),
                ]);
            }

            if (! in_array($status, ProjectTask::availableStatuses(), true)) {
                fclose($handle);

                throw ValidationException::withMessages([
                    'file' => __('ui.projects.csv_import_row_error', [
                        'row' => $rowNumber,
                        'message' => __('validation.in', ['attribute' => 'status']),
                    ]),
                ]);
            }

            if (! in_array($importance, ProjectTask::availableImportances(), true)) {
                fclose($handle);

                throw ValidationException::withMessages([
                    'file' => __('ui.projects.csv_import_row_error', [
                        'row' => $rowNumber,
                        'message' => __('validation.in', ['attribute' => 'importance']),
                    ]),
                ]);
            }

            if ($complexity < 1 || $complexity > 10) {
                fclose($handle);

                throw ValidationException::withMessages([
                    'file' => __('ui.projects.csv_import_row_error', [
                        'row' => $rowNumber,
                        'message' => __('validation.between.numeric', [
                            'attribute' => 'complexity',
                            'min' => 1,
                            'max' => 10,
                        ]),
                    ]),
                ]);
            }

            if ($sortOrder < 0) {
                fclose($handle);

                throw ValidationException::withMessages([
                    'file' => __('ui.projects.csv_import_row_error', [
                        'row' => $rowNumber,
                        'message' => __('validation.min.numeric', [
                            'attribute' => 'sort order',
                            'min' => 0,
                        ]),
                    ]),
                ]);
            }

            $rows[] = [
                'row' => $rowNumber,
                'task_key' => $taskKey,
                'parent_task_key' => $mappedRow['parent_task_key'] !== '' ? $mappedRow['parent_task_key'] : null,
                'title' => Str::limit($mappedRow['title'], 255, ''),
                'description' => $mappedRow['description'] !== '' ? Str::limit($mappedRow['description'], 10000, '') : null,
                'status' => $status,
                'importance' => $importance,
                'complexity' => $complexity,
                'due_at' => $this->parseDueAt($mappedRow['due_at'], $rowNumber),
                'sort_order' => $sortOrder,
                'assignee_user_id' => $assigneeUserId,
                'co_assignee_user_ids' => $coAssigneeUserIds,
            ];

            $seenTaskKeys[$taskKey] = true;
        }

        fclose($handle);

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => __('ui.projects.csv_import_empty'),
            ]);
        }

        $this->validateParentKeys($rows);

        return $rows;
    }

    private function normalizeHeaderValue(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return Str::of($value)
            ->replace("\xEF\xBB\xBF", '')
            ->trim()
            ->lower()
            ->value();
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        return collect($row)->every(fn (mixed $value): bool => trim((string) $value) === '');
    }

    private function parseDueAt(string $value, int $rowNumber): ?CarbonInterface
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'file' => __('ui.projects.csv_import_row_error', [
                    'row' => $rowNumber,
                    'message' => __('validation.date', ['attribute' => 'due_at']),
                ]),
            ]);
        }
    }

    /**
     * @param  array<int, array{task_key: string, parent_task_key: string|null}>  $rows
     */
    private function validateParentKeys(array $rows): void
    {
        $rowsByKey = collect($rows)->keyBy('task_key');

        foreach ($rows as $row) {
            $parentTaskKey = $row['parent_task_key'];

            if ($parentTaskKey === null) {
                continue;
            }

            if (! $rowsByKey->has($parentTaskKey)) {
                throw ValidationException::withMessages([
                    'file' => __('ui.projects.csv_import_unknown_parent', ['key' => $parentTaskKey]),
                ]);
            }
        }

        $visited = [];
        $visiting = [];

        foreach ($rows as $row) {
            $this->detectParentCycle($row['task_key'], $rowsByKey, $visited, $visiting);
        }
    }

    /**
     * @param  Collection<string, array{task_key: string, parent_task_key: string|null}>  $rowsByKey
     * @param  array<string, bool>  $visited
     * @param  array<string, bool>  $visiting
     */
    private function detectParentCycle(
        string $taskKey,
        Collection $rowsByKey,
        array &$visited,
        array &$visiting,
    ): void {
        if (isset($visited[$taskKey])) {
            return;
        }

        if (isset($visiting[$taskKey])) {
            throw ValidationException::withMessages([
                'file' => __('ui.projects.csv_import_cycle'),
            ]);
        }

        $visiting[$taskKey] = true;

        $parentTaskKey = $rowsByKey[$taskKey]['parent_task_key'];

        if ($parentTaskKey !== null) {
            $this->detectParentCycle($parentTaskKey, $rowsByKey, $visited, $visiting);
        }

        unset($visiting[$taskKey]);
        $visited[$taskKey] = true;
    }

    private function resolveAssigneeUserId(string $email, ?Project $project, int $rowNumber): ?int
    {
        if ($email === '') {
            return null;
        }

        $user = User::query()
            ->where('email', $email)
            ->where('is_active', true)
            ->first();

        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'file' => __('ui.projects.csv_import_row_error', [
                    'row' => $rowNumber,
                    'message' => __('ui.projects.csv_import_invalid_assignee', ['email' => $email]),
                ]),
            ]);
        }

        if ($project !== null && ! $project->members()->whereKey($user->id)->exists()) {
            throw ValidationException::withMessages([
                'file' => __('ui.projects.csv_import_row_error', [
                    'row' => $rowNumber,
                    'message' => __('ui.projects.csv_import_assignee_not_member', ['email' => $email]),
                ]),
            ]);
        }

        return $user->id;
    }

    /**
     * @return array<int, int>
     */
    private function resolveCoAssigneeUserIds(
        string $emails,
        ?Project $project,
        int $rowNumber,
        ?int $assigneeUserId,
    ): array {
        if ($emails === '') {
            return [];
        }

        $coAssigneeUserIds = collect(explode('|', $emails))
            ->map(fn (string $email): string => trim($email))
            ->filter()
            ->map(function (string $email) use ($project, $rowNumber): int {
                $user = User::query()
                    ->where('email', $email)
                    ->where('is_active', true)
                    ->first();

                if (! $user instanceof User) {
                    throw ValidationException::withMessages([
                        'file' => __('ui.projects.csv_import_row_error', [
                            'row' => $rowNumber,
                            'message' => __('ui.projects.csv_import_invalid_co_assignee', ['email' => $email]),
                        ]),
                    ]);
                }

                if ($project !== null && ! $project->members()->whereKey($user->id)->exists()) {
                    throw ValidationException::withMessages([
                        'file' => __('ui.projects.csv_import_row_error', [
                            'row' => $rowNumber,
                            'message' => __('ui.projects.csv_import_co_assignee_not_member', ['email' => $email]),
                        ]),
                    ]);
                }

                return $user->id;
            })
            ->unique()
            ->values()
            ->all();

        if ($assigneeUserId !== null && in_array($assigneeUserId, $coAssigneeUserIds, true)) {
            throw ValidationException::withMessages([
                'file' => __('ui.projects.csv_import_row_error', [
                    'row' => $rowNumber,
                    'message' => __('ui.projects.csv_import_duplicate_assignment'),
                ]),
            ]);
        }

        return $coAssigneeUserIds;
    }
}
