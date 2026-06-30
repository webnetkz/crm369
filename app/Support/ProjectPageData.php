<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Support\Collection;

class ProjectPageData
{
    /**
     * @return array<string, mixed>
     */
    public function build(User $viewer, ?Project $activeProject = null, ?ProjectTask $activeTask = null): array
    {
        $workspaceProjects = Project::query()
            ->visibleTo($viewer)
            ->with([
                'owner:id,name,last_name,email,avatar_path,avatar_scale',
                'members:id,name,last_name,email,avatar_path,avatar_scale',
            ])
            ->withCount([
                'members',
                'tasks',
                'tasks as open_tasks_count' => fn ($query) => $query->where('status', '!=', ProjectTask::STATUS_DONE),
                'tasks as completed_tasks_count' => fn ($query) => $query->where('status', ProjectTask::STATUS_DONE),
            ])
            ->orderBy('is_archived')
            ->orderBy('name')
            ->get();

        $resolvedProject = $activeProject
            ? $workspaceProjects->firstWhere('id', $activeProject->id)
            : null;

        if (! $resolvedProject && $activeTask?->project_id !== null) {
            $resolvedProject = $workspaceProjects->firstWhere('id', $activeTask->project_id);
        }

        $workspaceTasks = ProjectTask::query()
            ->visibleTo($viewer)
            ->with([
                'project:id,name,slug,is_archived,owner_user_id',
                'creator:id,name,last_name,email,avatar_path,avatar_scale',
                'assignee:id,name,last_name,email,avatar_path,avatar_scale',
                'coAssignees:id,name,last_name,email,avatar_path,avatar_scale',
            ])
            ->when(
                $resolvedProject !== null,
                fn ($query) => $query->where('project_id', $resolvedProject->id),
            )
            ->orderBy('status')
            ->orderBy('sort_order')
            ->orderBy('due_at')
            ->orderByDesc('created_at')
            ->get();

        $resolvedTask = null;

        if ($activeTask !== null) {
            $resolvedTask = $workspaceTasks->firstWhere('id', $activeTask->id);
        }

        $standaloneTasks = $workspaceTasks->whereNull('project_id')->values();

        return [
            'projects' => $workspaceProjects
                ->map(fn (Project $project): array => $this->projectListItem($project))
                ->values()
                ->all(),
            'taskGroups' => $this->taskGroups($workspaceProjects, $workspaceTasks, $resolvedProject),
            'activeProject' => $resolvedProject ? $this->activeProject($resolvedProject, $workspaceTasks) : null,
            'activeTask' => $resolvedTask ? $this->activeTask($resolvedTask, $workspaceTasks) : null,
            'availableUsers' => User::query()
                ->select(['id', 'name', 'last_name', 'email', 'avatar_path', 'avatar_scale'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => $this->userSummary($user))
                ->values()
                ->all(),
            'availableProjects' => $workspaceProjects
                ->map(fn (Project $project): array => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'is_archived' => $project->is_archived,
                    'members' => $project->members
                        ->map(fn (User $user): array => $this->userSummary($user))
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'can' => [
                'createProject' => true,
                'createTask' => true,
                'manageProject' => $resolvedProject ? $viewer->canManageProject($resolvedProject) : false,
                'manageTask' => $resolvedTask ? $viewer->canManageTask($resolvedTask) : false,
                'workOnActiveProject' => $resolvedProject ? $viewer->canWorkOnProject($resolvedProject) : false,
            ],
            'taskOptions' => [
                'statuses' => collect(ProjectTask::availableStatuses())
                    ->map(fn (string $status): array => [
                        'value' => $status,
                        'label' => __('ui.projects.status_'.$status),
                    ])
                    ->values()
                    ->all(),
                'importances' => collect(ProjectTask::availableImportances())
                    ->map(fn (string $importance): array => [
                        'value' => $importance,
                        'label' => __('ui.projects.importance_'.$importance),
                    ])
                    ->values()
                    ->all(),
                'complexity' => range(1, 10),
            ],
            'workspaceSummary' => [
                'standalone_tasks_count' => $standaloneTasks->count(),
                'standalone_open_tasks_count' => $standaloneTasks->where('status', '!=', ProjectTask::STATUS_DONE)->count(),
                'standalone_completed_tasks_count' => $standaloneTasks->where('status', ProjectTask::STATUS_DONE)->count(),
            ],
        ];
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @param  Collection<int, ProjectTask>  $tasks
     * @return array<int, array<string, mixed>>
     */
    private function taskGroups(Collection $projects, Collection $tasks, ?Project $resolvedProject): array
    {
        if ($resolvedProject !== null) {
            return [
                $this->projectTaskGroup($resolvedProject, $tasks),
            ];
        }

        return [
            $this->standaloneTaskGroup($tasks->whereNull('project_id')->values()),
            ...$projects
                ->map(fn (Project $project): array => $this->projectTaskGroup(
                    $project,
                    $tasks->where('project_id', $project->id)->values(),
                ))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, ProjectTask>  $tasks
     * @return array<string, mixed>
     */
    private function standaloneTaskGroup(Collection $tasks): array
    {
        return [
            'key' => 'standalone',
            'kind' => 'standalone',
            'title' => __('ui.projects.no_project_group'),
            'description' => __('ui.projects.no_project_group_description'),
            'project' => null,
            'tasks_count' => $tasks->count(),
            'open_tasks_count' => $tasks->where('status', '!=', ProjectTask::STATUS_DONE)->count(),
            'completed_tasks_count' => $tasks->where('status', ProjectTask::STATUS_DONE)->count(),
            'tasks' => $this->taskTree($tasks),
        ];
    }

    /**
     * @param  Collection<int, ProjectTask>  $tasks
     * @return array<string, mixed>
     */
    private function projectTaskGroup(Project $project, Collection $tasks): array
    {
        return [
            'key' => 'project-'.$project->id,
            'kind' => 'project',
            'title' => $project->name,
            'description' => $project->description,
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'is_archived' => $project->is_archived,
                'owner' => $this->userSummary($project->owner),
                'members_count' => $project->members_count,
            ],
            'tasks_count' => $tasks->count(),
            'open_tasks_count' => $tasks->where('status', '!=', ProjectTask::STATUS_DONE)->count(),
            'completed_tasks_count' => $tasks->where('status', ProjectTask::STATUS_DONE)->count(),
            'tasks' => $this->taskTree($tasks),
        ];
    }

    /**
     * @param  Collection<int, ProjectTask>  $tasks
     * @return array<int, array<string, mixed>>
     */
    private function taskTree(Collection $tasks): array
    {
        $taskMap = $tasks->keyBy('id');
        $childrenByParent = $tasks->groupBy(fn (ProjectTask $task): string => (string) ($task->parent_task_id ?? 0));

        $serializeTask = function (ProjectTask $task) use (&$serializeTask, $childrenByParent, $taskMap): array {
            /** @var Collection<int, ProjectTask> $children */
            $children = $childrenByParent->get((string) $task->id, collect());
            $parentTask = $task->parent_task_id !== null ? $taskMap->get($task->parent_task_id) : null;

            return [
                'id' => $task->id,
                'project_id' => $task->project_id,
                'project_name' => $task->project?->name,
                'parent_task_id' => $task->parent_task_id,
                'parent_task_title' => $parentTask?->title,
                'title' => $task->title,
                'status' => $task->status,
                'importance' => $task->importance,
                'complexity' => $task->complexity,
                'due_at' => $task->due_at?->toISOString(),
                'completed_at' => $task->completed_at?->toISOString(),
                'assignee' => $this->userSummary($task->assignee),
                'creator' => $this->userSummary($task->creator),
                'co_assignees_count' => $task->coAssignees->count(),
                'subtasks_count' => $children->count(),
                'updated_at' => $task->updated_at?->toISOString(),
                'subtasks' => $children
                    ->map(fn (ProjectTask $childTask): array => $serializeTask($childTask))
                    ->values()
                    ->all(),
            ];
        };

        /** @var Collection<int, ProjectTask> $rootTasks */
        $rootTasks = $tasks
            ->filter(fn (ProjectTask $task): bool => $task->parent_task_id === null)
            ->values();

        return $rootTasks
            ->map(fn (ProjectTask $task): array => $serializeTask($task))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ProjectTask>  $tasks
     * @return array<string, mixed>
     */
    private function activeProject(Project $project, Collection $tasks): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'slug' => $project->slug,
            'description' => $project->description,
            'is_archived' => $project->is_archived,
            'owner' => $this->userSummary($project->owner),
            'members' => $project->members
                ->map(fn (User $user): array => $this->userSummary($user))
                ->values()
                ->all(),
            'tasks' => $this->taskTree($tasks->where('project_id', $project->id)->values()),
        ];
    }

    /**
     * @param  Collection<int, ProjectTask>  $workspaceTasks
     * @return array<string, mixed>
     */
    private function activeTask(ProjectTask $task, Collection $workspaceTasks): array
    {
        $taskMap = $workspaceTasks->keyBy('id');
        $subtasks = $workspaceTasks
            ->where('parent_task_id', $task->id)
            ->values();

        return [
            'id' => $task->id,
            'project_id' => $task->project_id,
            'project_name' => $task->project?->name,
            'parent_task_id' => $task->parent_task_id,
            'parent_task' => $task->parent_task_id !== null
                ? [
                    'id' => $task->parent_task_id,
                    'title' => $taskMap->get($task->parent_task_id)?->title,
                ]
                : null,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'importance' => $task->importance,
            'complexity' => $task->complexity,
            'due_at' => $task->due_at?->toISOString(),
            'completed_at' => $task->completed_at?->toISOString(),
            'sort_order' => $task->sort_order,
            'creator' => $this->userSummary($task->creator),
            'assignee' => $this->userSummary($task->assignee),
            'co_assignees' => $task->coAssignees
                ->map(fn (User $user): array => $this->userSummary($user))
                ->values()
                ->all(),
            'subtasks' => $subtasks
                ->map(fn (ProjectTask $childTask): array => [
                    'id' => $childTask->id,
                    'project_id' => $childTask->project_id,
                    'project_name' => $childTask->project?->name,
                    'parent_task_id' => $childTask->parent_task_id,
                    'parent_task_title' => $task->title,
                    'title' => $childTask->title,
                    'status' => $childTask->status,
                    'importance' => $childTask->importance,
                    'complexity' => $childTask->complexity,
                    'due_at' => $childTask->due_at?->toISOString(),
                    'completed_at' => $childTask->completed_at?->toISOString(),
                    'assignee' => $this->userSummary($childTask->assignee),
                    'creator' => $this->userSummary($childTask->creator),
                    'co_assignees_count' => $childTask->coAssignees->count(),
                    'subtasks_count' => $workspaceTasks->where('parent_task_id', $childTask->id)->count(),
                    'updated_at' => $childTask->updated_at?->toISOString(),
                    'subtasks' => [],
                ])
                ->values()
                ->all(),
            'updated_at' => $task->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectListItem(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'slug' => $project->slug,
            'description' => $project->description,
            'is_archived' => $project->is_archived,
            'members_count' => $project->members_count,
            'tasks_count' => $project->tasks_count,
            'open_tasks_count' => $project->open_tasks_count,
            'completed_tasks_count' => $project->completed_tasks_count,
            'updated_at' => $project->updated_at?->toISOString(),
            'owner' => $this->userSummary($project->owner),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function userSummary(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'avatar_scale' => $user->avatar_scale,
        ];
    }
}
