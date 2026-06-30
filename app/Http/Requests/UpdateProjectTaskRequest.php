<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Support\ApiRequestContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProjectTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $projectTask = $this->route('projectTask');
        $routeProject = $this->route('project');
        $user = $this->user() !== null ? ApiRequestContext::subject($this) : null;

        if (! ($projectTask instanceof ProjectTask) || $user === null) {
            return false;
        }

        if ($routeProject instanceof Project && $projectTask->project_id !== $routeProject->id) {
            return false;
        }

        return $user->canManageTask($projectTask);
    }

    protected function prepareForValidation(): void
    {
        $routeProject = $this->route('project');

        $this->merge([
            'project_id' => $routeProject instanceof Project
                ? $routeProject->id
                : (is_numeric($this->input('project_id')) ? (int) $this->input('project_id') : null),
            'parent_task_id' => is_numeric($this->input('parent_task_id')) ? (int) $this->input('parent_task_id') : null,
            'title' => is_string($this->input('title')) ? trim($this->input('title')) : $this->input('title'),
            'description' => $this->normalizeNullableString($this->input('description')),
            'status' => is_string($this->input('status')) ? trim($this->input('status')) : $this->input('status'),
            'importance' => is_string($this->input('importance')) ? trim($this->input('importance')) : $this->input('importance'),
            'complexity' => (int) $this->input('complexity', 5),
            'sort_order' => max(0, (int) $this->input('sort_order', 0)),
            'assignee_user_id' => is_numeric($this->input('assignee_user_id')) ? (int) $this->input('assignee_user_id') : null,
            'co_assignee_user_ids' => array_values(array_filter(
                (array) $this->input('co_assignee_user_ids', []),
                fn (mixed $value): bool => is_numeric($value) && (int) $value > 0,
            )),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'parent_task_id' => ['nullable', 'integer', 'exists:project_tasks,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', 'string', Rule::in(ProjectTask::availableStatuses())],
            'importance' => ['required', 'string', Rule::in(ProjectTask::availableImportances())],
            'complexity' => ['required', 'integer', 'between:1,10'],
            'due_at' => ['nullable', 'date'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'assignee_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'co_assignee_user_ids' => ['nullable', 'array', 'list'],
            'co_assignee_user_ids.*' => ['integer', 'distinct:strict', 'exists:users,id'],
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateMembership($validator)];
    }

    public function project(): ?Project
    {
        return $this->resolvedProject();
    }

    public function parentTaskId(): ?int
    {
        $parentTaskId = $this->validated('parent_task_id');

        return is_numeric($parentTaskId) ? (int) $parentTaskId : null;
    }

    /**
     * @return array<int, int>
     */
    public function coAssigneeUserIds(): array
    {
        return collect($this->validated('co_assignee_user_ids', []))
            ->map(fn (mixed $value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function dueAt(): ?Carbon
    {
        $dueAt = $this->validated('due_at');

        return is_string($dueAt) && trim($dueAt) !== ''
            ? Carbon::parse($dueAt)
            : null;
    }

    private function validateMembership(Validator $validator): void
    {
        /** @var ProjectTask|null $currentTask */
        $currentTask = $this->route('projectTask');
        $user = $this->user() !== null ? ApiRequestContext::subject($this) : null;
        $project = $this->resolvedProject();
        $parentTask = $this->resolvedParentTask();

        if ($project && ! ($user?->canWorkOnProject($project) ?? false)) {
            $validator->errors()->add('project_id', __('ui.projects.validation_project_access'));
        }

        if ($parentTask && ! ($user?->canViewTask($parentTask) ?? false)) {
            $validator->errors()->add('parent_task_id', __('ui.projects.validation_parent_task_access'));
        }

        if ($currentTask && $parentTask && $currentTask->id === $parentTask->id) {
            $validator->errors()->add('parent_task_id', __('ui.projects.validation_parent_task_not_self'));
        }

        if ($currentTask && $parentTask && in_array($parentTask->id, $this->descendantTaskIds($currentTask), true)) {
            $validator->errors()->add('parent_task_id', __('ui.projects.validation_parent_task_cycle'));
        }

        if ($parentTask && $parentTask->project_id !== $this->projectId()) {
            $validator->errors()->add('parent_task_id', __('ui.projects.validation_parent_task_same_group'));
        }

        if (! $project) {
            $this->validateDuplicateAssignment($validator);

            return;
        }

        $memberIds = $project->members()->pluck('users.id')->all();
        $assigneeUserId = $this->validated('assignee_user_id');

        if ($assigneeUserId !== null && ! in_array((int) $assigneeUserId, $memberIds, true)) {
            $validator->errors()->add('assignee_user_id', __('ui.projects.validation_assignee_member'));
        }

        foreach ($this->coAssigneeUserIds() as $index => $userId) {
            if (! in_array($userId, $memberIds, true)) {
                $validator->errors()->add("co_assignee_user_ids.$index", __('ui.projects.validation_co_assignee_member'));
            }
        }

        $this->validateDuplicateAssignment($validator);
    }

    private function validateDuplicateAssignment(Validator $validator): void
    {
        $assigneeUserId = $this->validated('assignee_user_id');

        if ($assigneeUserId !== null && in_array((int) $assigneeUserId, $this->coAssigneeUserIds(), true)) {
            $validator->errors()->add('co_assignee_user_ids', __('ui.projects.validation_duplicate_assignment'));
        }
    }

    /**
     * @return array<int, int>
     */
    private function descendantTaskIds(ProjectTask $task): array
    {
        $descendantIds = [];
        $pendingParentIds = [$task->id];

        while ($pendingParentIds !== []) {
            $childIds = ProjectTask::query()
                ->whereIn('parent_task_id', $pendingParentIds)
                ->pluck('id')
                ->map(fn (mixed $value): int => (int) $value)
                ->all();

            $nextChildIds = array_values(array_diff($childIds, $descendantIds));

            if ($nextChildIds === []) {
                break;
            }

            $descendantIds = [...$descendantIds, ...$nextChildIds];
            $pendingParentIds = $nextChildIds;
        }

        return $descendantIds;
    }

    private function projectId(): ?int
    {
        $projectId = $this->validated('project_id');

        return is_numeric($projectId) ? (int) $projectId : null;
    }

    private function resolvedProject(): ?Project
    {
        $projectId = $this->input('project_id');

        return is_numeric($projectId)
            ? Project::query()->find((int) $projectId)
            : null;
    }

    private function resolvedParentTask(): ?ProjectTask
    {
        $parentTaskId = $this->input('parent_task_id');

        return is_numeric($parentTaskId)
            ? ProjectTask::query()->find((int) $parentTaskId)
            : null;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
