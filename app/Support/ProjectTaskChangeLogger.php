<?php

namespace App\Support;

use App\Models\ProjectTask;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class ProjectTaskChangeLogger
{
    public function __construct(
        private ChatMessageSender $chatMessageSender,
        private TaskConversationManager $taskConversationManager,
    ) {}

    /**
     * @return array{
     *     project_id: int|null,
     *     project_name: string|null,
     *     parent_task_id: int|null,
     *     parent_task_title: string|null,
     *     title: string,
     *     description: string|null,
     *     status: string,
     *     importance: string,
     *     complexity: int,
     *     due_at: string|null,
     *     sort_order: int,
     *     assignee_user_id: int|null,
     *     assignee_name: string|null,
     *     co_assignee_ids: array<int, int>,
     *     co_assignee_names: array<int, string>
     * }
     */
    public function snapshot(ProjectTask $task): array
    {
        $task->loadMissing([
            'project:id,name',
            'parentTask:id,title',
            'assignee:id,name,last_name',
            'coAssignees:id,name,last_name',
        ]);

        return [
            'project_id' => $task->project_id,
            'project_name' => $task->project?->name,
            'parent_task_id' => $task->parent_task_id,
            'parent_task_title' => $task->parentTask?->title,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'importance' => $task->importance,
            'complexity' => $task->complexity,
            'due_at' => $task->due_at?->toISOString(),
            'sort_order' => $task->sort_order,
            'assignee_user_id' => $task->assignee_user_id,
            'assignee_name' => $this->userName($task->assignee),
            'co_assignee_ids' => $task->coAssignees
                ->pluck('id')
                ->map(fn (mixed $value): int => (int) $value)
                ->sort()
                ->values()
                ->all(),
            'co_assignee_names' => $task->coAssignees
                ->map(fn (User $user): string => $this->userName($user))
                ->filter(fn (string $name): bool => $name !== '')
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array{
     *     project_id: int|null,
     *     project_name: string|null,
     *     parent_task_id: int|null,
     *     parent_task_title: string|null,
     *     title: string,
     *     description: string|null,
     *     status: string,
     *     importance: string,
     *     complexity: int,
     *     due_at: string|null,
     *     sort_order: int,
     *     assignee_user_id: int|null,
     *     assignee_name: string|null,
     *     co_assignee_ids: array<int, int>,
     *     co_assignee_names: array<int, string>
     * }  $beforeState
     */
    public function syncConversationAndLogChanges(
        array $beforeState,
        ProjectTask $task,
        User $actor,
    ): void {
        $task = $this->freshTask($task);

        $conversation = $this->taskConversationManager->ensureForTask($task, $actor);
        $afterState = $this->snapshot($this->freshTask($task));
        $locale = $actor->resolvedLanguage();
        $changeLines = $this->changeLines($beforeState, $afterState, $locale);

        if ($changeLines === []) {
            return;
        }

        $this->chatMessageSender->sendPlainText(
            $conversation,
            $actor,
            implode("\n", [
                __('ui.projects.task_change_log_heading', [], $locale),
                ...$changeLines,
            ]),
        );
    }

    private function freshTask(ProjectTask $task): ProjectTask
    {
        return $task->fresh([
            'project:id,name',
            'parentTask:id,title',
            'assignee:id,name,last_name',
            'coAssignees:id,name,last_name',
        ]) ?? $task;
    }

    /**
     * @param  array{
     *     project_id: int|null,
     *     project_name: string|null,
     *     parent_task_id: int|null,
     *     parent_task_title: string|null,
     *     title: string,
     *     description: string|null,
     *     status: string,
     *     importance: string,
     *     complexity: int,
     *     due_at: string|null,
     *     sort_order: int,
     *     assignee_user_id: int|null,
     *     assignee_name: string|null,
     *     co_assignee_ids: array<int, int>,
     *     co_assignee_names: array<int, string>
     * }  $beforeState
     * @param  array{
     *     project_id: int|null,
     *     project_name: string|null,
     *     parent_task_id: int|null,
     *     parent_task_title: string|null,
     *     title: string,
     *     description: string|null,
     *     status: string,
     *     importance: string,
     *     complexity: int,
     *     due_at: string|null,
     *     sort_order: int,
     *     assignee_user_id: int|null,
     *     assignee_name: string|null,
     *     co_assignee_ids: array<int, int>,
     *     co_assignee_names: array<int, string>
     * }  $afterState
     * @return array<int, string>
     */
    private function changeLines(array $beforeState, array $afterState, string $locale): array
    {
        $lines = [];

        $this->appendLine(
            $lines,
            __('ui.projects.task_location', [], $locale),
            $beforeState['project_id'],
            $afterState['project_id'],
            $this->projectLabel($beforeState['project_name'], $locale),
            $this->projectLabel($afterState['project_name'], $locale),
        );
        $this->appendLine(
            $lines,
            __('ui.projects.parent_task', [], $locale),
            $beforeState['parent_task_id'],
            $afterState['parent_task_id'],
            $this->quotedText($this->parentTaskLabel($beforeState['parent_task_title'], $locale)),
            $this->quotedText($this->parentTaskLabel($afterState['parent_task_title'], $locale)),
        );
        $this->appendLine(
            $lines,
            __('ui.projects.task_title', [], $locale),
            $beforeState['title'],
            $afterState['title'],
            $this->quotedText($beforeState['title']),
            $this->quotedText($afterState['title']),
        );
        $this->appendLine(
            $lines,
            __('ui.projects.description_label', [], $locale),
            $beforeState['description'],
            $afterState['description'],
            $this->quotedText($this->descriptionLabel($beforeState['description'], $locale)),
            $this->quotedText($this->descriptionLabel($afterState['description'], $locale)),
        );
        $this->appendLine(
            $lines,
            __('ui.projects.status', [], $locale),
            $beforeState['status'],
            $afterState['status'],
            $this->statusLabel($beforeState['status'], $locale),
            $this->statusLabel($afterState['status'], $locale),
        );
        $this->appendLine(
            $lines,
            __('ui.projects.importance', [], $locale),
            $beforeState['importance'],
            $afterState['importance'],
            $this->importanceLabel($beforeState['importance'], $locale),
            $this->importanceLabel($afterState['importance'], $locale),
        );
        $this->appendLine(
            $lines,
            __('ui.projects.complexity', [], $locale),
            $beforeState['complexity'],
            $afterState['complexity'],
            (string) $beforeState['complexity'],
            (string) $afterState['complexity'],
        );
        $this->appendLine(
            $lines,
            __('ui.projects.due_date', [], $locale),
            $beforeState['due_at'],
            $afterState['due_at'],
            $this->dateTimeLabel($beforeState['due_at'], $locale),
            $this->dateTimeLabel($afterState['due_at'], $locale),
        );
        $this->appendLine(
            $lines,
            __('ui.projects.sort_order', [], $locale),
            $beforeState['sort_order'],
            $afterState['sort_order'],
            (string) $beforeState['sort_order'],
            (string) $afterState['sort_order'],
        );
        $this->appendLine(
            $lines,
            __('ui.projects.assignee', [], $locale),
            $beforeState['assignee_user_id'],
            $afterState['assignee_user_id'],
            $this->assigneeLabel($beforeState['assignee_name'], $locale),
            $this->assigneeLabel($afterState['assignee_name'], $locale),
        );
        $this->appendLine(
            $lines,
            __('ui.projects.co_assignees', [], $locale),
            $beforeState['co_assignee_ids'],
            $afterState['co_assignee_ids'],
            $this->coAssigneeLabel($beforeState['co_assignee_names'], $locale),
            $this->coAssigneeLabel($afterState['co_assignee_names'], $locale),
        );

        return $lines;
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function appendLine(
        array &$lines,
        string $label,
        mixed $beforeValue,
        mixed $afterValue,
        string $beforeDisplay,
        string $afterDisplay,
    ): void {
        if ($beforeValue === $afterValue) {
            return;
        }

        $lines[] = sprintf('- %s: %s -> %s', $label, $beforeDisplay, $afterDisplay);
    }

    private function projectLabel(?string $projectName, string $locale): string
    {
        return $projectName !== null && trim($projectName) !== ''
            ? $this->quotedText($projectName)
            : __('ui.projects.standalone_task', [], $locale);
    }

    private function parentTaskLabel(?string $title, string $locale): string
    {
        return $title !== null && trim($title) !== ''
            ? $title
            : __('ui.projects.no_parent_task', [], $locale);
    }

    private function descriptionLabel(?string $description, string $locale): string
    {
        if ($description === null || trim($description) === '') {
            return __('ui.projects.empty_task_description', [], $locale);
        }

        return Str::limit(preg_replace('/\s+/u', ' ', trim($description)) ?? trim($description), 120);
    }

    private function statusLabel(string $status, string $locale): string
    {
        return __('ui.projects.status_'.$status, [], $locale);
    }

    private function importanceLabel(string $importance, string $locale): string
    {
        return __('ui.projects.importance_'.$importance, [], $locale);
    }

    private function dateTimeLabel(?string $value, string $locale): string
    {
        if ($value === null) {
            return __('ui.common.not_specified', [], $locale);
        }

        return $this->formatDateTime(Carbon::parse($value));
    }

    private function assigneeLabel(?string $name, string $locale): string
    {
        return $name !== null && trim($name) !== ''
            ? $name
            : __('ui.projects.unassigned', [], $locale);
    }

    /**
     * @param  array<int, string>  $names
     */
    private function coAssigneeLabel(array $names, string $locale): string
    {
        return $names !== []
            ? implode(', ', $names)
            : __('ui.projects.no_co_assignees', [], $locale);
    }

    private function quotedText(string $value): string
    {
        return '"'.str_replace('"', '\"', $value).'"';
    }

    private function formatDateTime(CarbonInterface $value): string
    {
        return $value->format('d.m.Y H:i');
    }

    private function userName(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return trim($user->name.' '.($user->last_name ?? ''));
    }
}
