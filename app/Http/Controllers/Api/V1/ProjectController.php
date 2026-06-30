<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\StoreProjectTaskRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Requests\UpdateProjectTaskRequest;
use App\Http\Resources\ApiProjectResource;
use App\Http\Resources\ApiProjectTaskResource;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskStage;
use App\Support\ApiRequestContext;
use App\Support\ProjectPageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request, ProjectPageData $pageData): JsonResponse
    {
        $user = ApiRequestContext::subject($request);

        return response()->json([
            'data' => $pageData->build($user),
        ]);
    }

    public function show(Request $request, Project $project, ProjectPageData $pageData): JsonResponse
    {
        $visibleProject = $this->visibleProject($request, $project);
        $user = ApiRequestContext::subject($request);

        return response()->json([
            'data' => $pageData->build($user, $visibleProject),
        ]);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $user = ApiRequestContext::subject($request);

        $project = Project::query()->create([
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug'),
            'description' => $request->validated('description'),
            'is_archived' => $request->boolean('is_archived'),
            'owner_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);

        $project->members()->sync($request->memberUserIds());

        return response()->json([
            'message' => __('ui.projects.project_created_success'),
            'data' => (new ApiProjectResource($this->loadProject($project)))->resolve(),
        ], 201);
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $visibleProject = $this->visibleProject($request, $project);
        $user = ApiRequestContext::subject($request);
        abort_unless($user->canManageProject($visibleProject), 403);

        $visibleProject->update([
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug'),
            'description' => $request->validated('description'),
            'is_archived' => $request->boolean('is_archived'),
            'updated_by_user_id' => $user->id,
        ]);

        $visibleProject->members()->sync($request->memberUserIds());

        return response()->json([
            'message' => __('ui.projects.project_updated_success'),
            'data' => (new ApiProjectResource($this->loadProject($visibleProject->fresh())))->resolve(),
        ]);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        $visibleProject = $this->visibleProject($request, $project);
        abort_unless(ApiRequestContext::subject($request)->canManageProject($visibleProject), 403);

        $deletedId = $visibleProject->id;
        $visibleProject->delete();

        return response()->json([
            'message' => __('ui.projects.project_deleted_success'),
            'data' => [
                'id' => $deletedId,
            ],
        ]);
    }

    public function storeTask(StoreProjectTaskRequest $request): JsonResponse
    {
        $user = ApiRequestContext::subject($request);
        $project = $request->project();

        if ($project !== null) {
            $project = $this->visibleProject($request, $project);
            abort_unless($user->canWorkOnProject($project), 403);
        }

        $task = $this->createTask($request, $project);
        $task->coAssignees()->sync($request->coAssigneeUserIds());

        return response()->json([
            'message' => __('ui.projects.task_created_success'),
            'data' => (new ApiProjectTaskResource($this->loadTask($task)))->resolve(),
        ], 201);
    }

    public function showTask(Request $request, ProjectTask $projectTask, ProjectPageData $pageData): JsonResponse
    {
        $visibleTask = $this->visibleTask($request, $projectTask);
        $activeProject = $visibleTask->project_id !== null
            ? $this->visibleProject($request, $visibleTask->project)
            : null;
        $user = ApiRequestContext::subject($request);

        return response()->json([
            'data' => $pageData->build($user, $activeProject, $visibleTask),
        ]);
    }

    public function updateTask(UpdateProjectTaskRequest $request, ProjectTask $projectTask): JsonResponse
    {
        $visibleTask = $this->visibleTask($request, $projectTask);
        $user = ApiRequestContext::subject($request);
        abort_unless($user->canManageTask($visibleTask), 403);

        $targetProject = $request->project();

        if ($targetProject !== null) {
            $targetProject = $this->visibleProject($request, $targetProject);
            abort_unless($user->canWorkOnProject($targetProject), 403);
        }

        $this->fillTask($visibleTask, $request, $targetProject);
        $visibleTask->coAssignees()->sync($request->coAssigneeUserIds());

        return response()->json([
            'message' => __('ui.projects.task_updated_success'),
            'data' => (new ApiProjectTaskResource($this->loadTask($visibleTask->fresh())))->resolve(),
        ]);
    }

    public function destroyTask(Request $request, ProjectTask $projectTask): JsonResponse
    {
        $visibleTask = $this->visibleTask($request, $projectTask);
        $user = ApiRequestContext::subject($request);
        abort_unless($user->canManageTask($visibleTask), 403);

        $visibleTask->update([
            'updated_by_user_id' => $user->id,
        ]);

        $deletedId = $visibleTask->id;
        $visibleTask->delete();

        return response()->json([
            'message' => __('ui.projects.task_deleted_success'),
            'data' => [
                'id' => $deletedId,
            ],
        ]);
    }

    private function createTask(StoreProjectTaskRequest $request, ?Project $project = null): ProjectTask
    {
        $user = ApiRequestContext::subject($request);

        return ProjectTask::query()->create([
            'project_id' => $project?->id,
            'parent_task_id' => $request->parentTaskId(),
            'creator_user_id' => $user->id,
            'assignee_user_id' => $request->validated('assignee_user_id'),
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'status' => $request->validated('status'),
            'importance' => $request->validated('importance'),
            'complexity' => $request->validated('complexity'),
            'due_at' => $request->dueAt(),
            'due_reminder_sent_at' => null,
            'completed_at' => ProjectTaskStage::isCompletedSlug((string) $request->validated('status')) ? now() : null,
            'sort_order' => $request->validated('sort_order'),
            'updated_by_user_id' => $user->id,
        ]);
    }

    private function fillTask(ProjectTask $task, UpdateProjectTaskRequest $request, ?Project $project = null): void
    {
        $user = ApiRequestContext::subject($request);
        $dueAt = $request->dueAt();
        $assigneeUserId = $request->validated('assignee_user_id');

        $task->update([
            'project_id' => $project?->id,
            'parent_task_id' => $request->parentTaskId(),
            'assignee_user_id' => $assigneeUserId,
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'status' => $request->validated('status'),
            'importance' => $request->validated('importance'),
            'complexity' => $request->validated('complexity'),
            'due_at' => $dueAt,
            'due_reminder_sent_at' => $task->dueReminderNeedsReset($dueAt, $assigneeUserId)
                ? null
                : $task->due_reminder_sent_at,
            'completed_at' => ProjectTaskStage::isCompletedSlug((string) $request->validated('status'))
                ? ($task->completed_at ?? now())
                : null,
            'sort_order' => $request->validated('sort_order'),
            'updated_by_user_id' => $user->id,
        ]);
    }

    private function visibleProject(Request $request, Project $project): Project
    {
        return Project::query()
            ->visibleTo(ApiRequestContext::subject($request))
            ->findOrFail($project->id);
    }

    private function visibleTask(Request $request, ProjectTask $projectTask): ProjectTask
    {
        return ProjectTask::query()
            ->visibleTo(ApiRequestContext::subject($request))
            ->with([
                'project',
                'creator:id,name,last_name,email,avatar_path,avatar_scale,user_group_id',
                'assignee:id,name,last_name,email,avatar_path,avatar_scale,user_group_id',
                'coAssignees:id,name,last_name,email,avatar_path,avatar_scale,user_group_id',
            ])
            ->findOrFail($projectTask->id);
    }

    private function loadProject(Project $project): Project
    {
        $completedStatuses = ProjectTaskStage::completedSlugs();

        return $project->load([
            'owner:id,name,last_name,email,avatar_path,avatar_scale,user_group_id',
            'members:id,name,last_name,email,avatar_path,avatar_scale,user_group_id',
        ])->loadCount([
            'members',
            'tasks',
            'tasks as open_tasks_count' => fn ($query) => $query->when(
                $completedStatuses !== [],
                fn ($taskQuery) => $taskQuery->whereNotIn('status', $completedStatuses),
            ),
            'tasks as completed_tasks_count' => fn ($query) => $query->when(
                $completedStatuses !== [],
                fn ($taskQuery) => $taskQuery->whereIn('status', $completedStatuses),
            ),
        ]);
    }

    private function loadTask(ProjectTask $task): ProjectTask
    {
        return $task->load([
            'project:id,name,slug',
            'parentTask:id,title',
            'creator:id,name,last_name,email,avatar_path,avatar_scale,user_group_id',
            'assignee:id,name,last_name,email,avatar_path,avatar_scale,user_group_id',
            'coAssignees:id,name,last_name,email,avatar_path,avatar_scale,user_group_id',
            'subtasks.project:id,name,slug',
            'subtasks.parentTask:id,title',
            'subtasks.creator:id,name,last_name,email,avatar_path,avatar_scale,user_group_id',
            'subtasks.assignee:id,name,last_name,email,avatar_path,avatar_scale,user_group_id',
            'subtasks.coAssignees:id,name,last_name,email,avatar_path,avatar_scale,user_group_id',
        ]);
    }
}
