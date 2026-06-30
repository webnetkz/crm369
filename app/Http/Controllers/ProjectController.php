<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\StoreProjectTaskRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Requests\UpdateProjectTaskRequest;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Support\ProjectPageData;
use App\Support\TaskConversationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request, ProjectPageData $pageData): Response
    {
        return Inertia::render('projects/Index', $pageData->build($request->user()));
    }

    public function showWorkspaceTask(Request $request, ProjectTask $projectTask, ProjectPageData $pageData): Response
    {
        $visibleTask = $this->visibleTask($request, $projectTask);
        $activeProject = $visibleTask->project_id !== null
            ? $this->visibleProject($request, $visibleTask->project)
            : null;

        return Inertia::render('projects/Index', $pageData->build($request->user(), $activeProject, $visibleTask));
    }

    public function show(Request $request, Project $project, ProjectPageData $pageData): Response
    {
        $visibleProject = $this->visibleProject($request, $project);

        return Inertia::render('projects/Index', $pageData->build($request->user(), $visibleProject));
    }

    public function task(Request $request, Project $project, ProjectTask $projectTask, ProjectPageData $pageData): Response
    {
        $visibleProject = $this->visibleProject($request, $project);
        $visibleTask = $this->visibleTaskInProject($visibleProject, $projectTask);

        return Inertia::render('projects/Index', $pageData->build($request->user(), $visibleProject, $visibleTask));
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $user = $request->user();

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

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.projects.project_created_success')]);

        return redirect()->route('projects.show', $project);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $visibleProject = $this->visibleProject($request, $project);
        abort_unless($request->user()->canManageProject($visibleProject), 403);

        $visibleProject->update([
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug'),
            'description' => $request->validated('description'),
            'is_archived' => $request->boolean('is_archived'),
            'updated_by_user_id' => $request->user()->id,
        ]);

        $visibleProject->members()->sync($request->memberUserIds());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.projects.project_updated_success')]);

        return back();
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $visibleProject = $this->visibleProject($request, $project);
        abort_unless($request->user()->canManageProject($visibleProject), 403);

        $visibleProject->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.projects.project_deleted_success')]);

        return redirect()->route('projects.index');
    }

    public function storeTask(
        StoreProjectTaskRequest $request,
        Project $project,
        TaskConversationManager $taskConversationManager,
    ): RedirectResponse
    {
        $visibleProject = $this->visibleProject($request, $project);
        abort_unless($request->user()->canWorkOnProject($visibleProject), 403);

        $task = $this->createTask($request, $visibleProject);
        $task->coAssignees()->sync($request->coAssigneeUserIds());
        $taskConversationManager->ensureForTask($task, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.projects.task_created_success')]);

        return redirect()->route('projects.tasks.show', [$visibleProject, $task]);
    }

    public function storeWorkspaceTask(
        StoreProjectTaskRequest $request,
        TaskConversationManager $taskConversationManager,
    ): RedirectResponse
    {
        $project = $request->project();

        if ($project !== null) {
            $project = $this->visibleProject($request, $project);
            abort_unless($request->user()->canWorkOnProject($project), 403);
        }

        $task = $this->createTask($request, $project);
        $task->coAssignees()->sync($request->coAssigneeUserIds());
        $taskConversationManager->ensureForTask($task, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.projects.task_created_success')]);

        return $this->redirectForTask($task);
    }

    public function updateTask(
        UpdateProjectTaskRequest $request,
        Project $project,
        ProjectTask $projectTask,
        TaskConversationManager $taskConversationManager,
    ): RedirectResponse
    {
        $visibleProject = $this->visibleProject($request, $project);
        $visibleTask = $this->visibleTaskInProject($visibleProject, $projectTask);
        abort_unless($request->user()->canWorkOnProject($visibleProject), 403);

        $this->fillTask($visibleTask, $request, $visibleProject);
        $visibleTask->coAssignees()->sync($request->coAssigneeUserIds());
        $taskConversationManager->ensureForTask($visibleTask, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.projects.task_updated_success')]);

        return back();
    }

    public function updateWorkspaceTask(
        UpdateProjectTaskRequest $request,
        ProjectTask $projectTask,
        TaskConversationManager $taskConversationManager,
    ): RedirectResponse
    {
        $visibleTask = $this->visibleTask($request, $projectTask);
        abort_unless($request->user()->canManageTask($visibleTask), 403);

        $targetProject = $request->project();

        if ($targetProject !== null) {
            $targetProject = $this->visibleProject($request, $targetProject);
            abort_unless($request->user()->canWorkOnProject($targetProject), 403);
        }

        $this->fillTask($visibleTask, $request, $targetProject);
        $visibleTask->coAssignees()->sync($request->coAssigneeUserIds());
        $taskConversationManager->ensureForTask($visibleTask, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.projects.task_updated_success')]);

        return $this->redirectForTask($visibleTask->fresh(['project']));
    }

    public function destroyTask(Request $request, Project $project, ProjectTask $projectTask): RedirectResponse
    {
        $visibleProject = $this->visibleProject($request, $project);
        $visibleTask = $this->visibleTaskInProject($visibleProject, $projectTask);
        abort_unless($request->user()->canWorkOnProject($visibleProject), 403);

        $visibleTask->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.projects.task_deleted_success')]);

        return redirect()->route('projects.show', $visibleProject);
    }

    public function destroyWorkspaceTask(Request $request, ProjectTask $projectTask): RedirectResponse
    {
        $visibleTask = $this->visibleTask($request, $projectTask);
        abort_unless($request->user()->canManageTask($visibleTask), 403);

        $redirect = $visibleTask->project_id !== null
            ? redirect()->route('projects.show', $visibleTask->project_id)
            : redirect()->route('projects.index');

        $visibleTask->update([
            'updated_by_user_id' => $request->user()->id,
        ]);
        $visibleTask->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.projects.task_deleted_success')]);

        return $redirect;
    }

    private function createTask(StoreProjectTaskRequest $request, ?Project $project = null): ProjectTask
    {
        return ProjectTask::query()->create([
            'project_id' => $project?->id,
            'parent_task_id' => $request->parentTaskId(),
            'creator_user_id' => $request->user()->id,
            'assignee_user_id' => $request->validated('assignee_user_id'),
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'status' => $request->validated('status'),
            'importance' => $request->validated('importance'),
            'complexity' => $request->validated('complexity'),
            'due_at' => $request->dueAt(),
            'due_reminder_sent_at' => null,
            'completed_at' => $request->validated('status') === ProjectTask::STATUS_DONE ? now() : null,
            'sort_order' => $request->validated('sort_order'),
            'updated_by_user_id' => $request->user()->id,
        ]);
    }

    private function fillTask(ProjectTask $task, UpdateProjectTaskRequest $request, ?Project $project = null): void
    {
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
            'completed_at' => $request->validated('status') === ProjectTask::STATUS_DONE ? ($task->completed_at ?? now()) : null,
            'sort_order' => $request->validated('sort_order'),
            'updated_by_user_id' => $request->user()->id,
        ]);
    }

    private function redirectForTask(ProjectTask $task): RedirectResponse
    {
        return redirect()->route('projects.workspace.tasks.show', $task);
    }

    private function visibleProject(Request $request, Project $project): Project
    {
        return Project::query()
            ->visibleTo($request->user())
            ->findOrFail($project->id);
    }

    private function visibleTask(Request $request, ProjectTask $projectTask): ProjectTask
    {
        return ProjectTask::query()
            ->visibleTo($request->user())
            ->with([
                'project',
                'creator:id,name,last_name,email,avatar_path,avatar_scale',
                'assignee:id,name,last_name,email,avatar_path,avatar_scale',
                'coAssignees:id,name,last_name,email,avatar_path,avatar_scale',
            ])
            ->findOrFail($projectTask->id);
    }

    private function visibleTaskInProject(Project $project, ProjectTask $projectTask): ProjectTask
    {
        return $project->tasks()
            ->with([
                'creator:id,name,last_name,email,avatar_path,avatar_scale',
                'assignee:id,name,last_name,email,avatar_path,avatar_scale',
                'coAssignees:id,name,last_name,email,avatar_path,avatar_scale',
            ])
            ->findOrFail($projectTask->id);
    }
}
