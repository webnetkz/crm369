<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportProjectTasksRequest;
use App\Http\Requests\MoveProjectTaskRequest;
use App\Http\Requests\MoveProjectTaskStagesRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\StoreProjectTaskRequest;
use App\Http\Requests\StoreProjectTaskStageRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Requests\UpdateProjectTaskRequest;
use App\Http\Requests\UpdateProjectTaskStageRequest;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskStage;
use App\Models\User;
use App\Support\ProjectPageData;
use App\Support\ProjectTaskAssignmentNotifier;
use App\Support\ProjectTaskChangeLogger;
use App\Support\ProjectTaskCsvService;
use App\Support\TaskConversationManager;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    public function index(Request $request, ProjectPageData $pageData): Response
    {
        return Inertia::render('projects/Index', $pageData->build($request->user()));
    }

    public function tasksIndex(Request $request, ProjectPageData $pageData): Response
    {
        return Inertia::render('projects/Index', $pageData->build(
            $request->user(),
            mode: ProjectPageData::MODE_TASKS,
            taskDisplayMode: $this->taskDisplayMode($request),
        ));
    }

    public function showWorkspaceTask(Request $request, ProjectTask $projectTask, ProjectPageData $pageData): Response
    {
        $visibleTask = $this->visibleTask($request, $projectTask);
        $activeProject = $visibleTask->project_id !== null
            ? $this->visibleProject($request, $visibleTask->project)
            : null;

        return Inertia::render('projects/Index', $pageData->build($request->user(), $activeProject, $visibleTask));
    }

    public function showStandaloneTask(Request $request, ProjectTask $projectTask, ProjectPageData $pageData): Response
    {
        $visibleTask = $this->visibleTask($request, $projectTask);
        abort_if($visibleTask->project_id !== null, 404);

        return Inertia::render('projects/Index', $pageData->build(
            $request->user(),
            activeTask: $visibleTask,
            mode: ProjectPageData::MODE_TASKS,
            taskDisplayMode: $this->taskDisplayMode($request),
        ));
    }

    public function exportStandaloneTasks(Request $request, ProjectTaskCsvService $projectTaskCsvService): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return $projectTaskCsvService->download(
            $this->standaloneTasksForExport($user),
            'standalone-tasks-'.now()->format('Y-m-d-H-i-s').'.csv',
        );
    }

    public function importStandaloneTasks(
        ImportProjectTasksRequest $request,
        ProjectTaskCsvService $projectTaskCsvService,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $importedCount = $projectTaskCsvService->import($request->file('file'), $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.projects.csv_import_success', ['count' => $importedCount]),
        ]);

        return back();
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

    public function exportProjectTasks(
        Request $request,
        Project $project,
        ProjectTaskCsvService $projectTaskCsvService,
    ): StreamedResponse {
        $visibleProject = $this->visibleProject($request, $project);

        return $projectTaskCsvService->download(
            $this->projectTasksForExport($request, $visibleProject),
            $visibleProject->slug.'-tasks-'.now()->format('Y-m-d-H-i-s').'.csv',
        );
    }

    public function importProjectTasks(
        ImportProjectTasksRequest $request,
        Project $project,
        ProjectTaskCsvService $projectTaskCsvService,
    ): RedirectResponse {
        $visibleProject = $this->visibleProject($request, $project);
        abort_unless($request->user()->canWorkOnProject($visibleProject), 403);

        $importedCount = $projectTaskCsvService->import($request->file('file'), $request->user(), $visibleProject);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.projects.csv_import_success', ['count' => $importedCount]),
        ]);

        return back();
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
        ProjectTaskAssignmentNotifier $taskAssignmentNotifier,
    ): RedirectResponse {
        $visibleProject = $this->visibleProject($request, $project);
        abort_unless($request->user()->canWorkOnProject($visibleProject), 403);

        $task = $this->createTask($request, $visibleProject);
        $task->coAssignees()->sync($request->coAssigneeUserIds());
        $taskConversationManager->ensureForTask($task, $request->user());
        $taskAssignmentNotifier->sendForManualCreation($task, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.projects.task_created_success')]);

        return redirect()->route('projects.tasks.show', [$visibleProject, $task]);
    }

    public function storeWorkspaceTask(
        StoreProjectTaskRequest $request,
        TaskConversationManager $taskConversationManager,
        ProjectTaskAssignmentNotifier $taskAssignmentNotifier,
    ): RedirectResponse {
        $project = $request->project();

        if ($this->isTasksMode($request)) {
            abort_if($project !== null, 404);
        }

        if ($project !== null) {
            $project = $this->visibleProject($request, $project);
            abort_unless($request->user()->canWorkOnProject($project), 403);
        }

        $task = $this->createTask($request, $project);
        $task->coAssignees()->sync($request->coAssigneeUserIds());
        $taskConversationManager->ensureForTask($task, $request->user());
        $taskAssignmentNotifier->sendForManualCreation($task, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.projects.task_created_success')]);

        return $this->redirectForTask($request, $task);
    }

    public function updateTask(
        UpdateProjectTaskRequest $request,
        Project $project,
        ProjectTask $projectTask,
        ProjectTaskChangeLogger $taskChangeLogger,
    ): RedirectResponse {
        $visibleProject = $this->visibleProject($request, $project);
        $visibleTask = $this->visibleTaskInProject($visibleProject, $projectTask);
        abort_unless($request->user()->canWorkOnProject($visibleProject), 403);

        $beforeState = $taskChangeLogger->snapshot($visibleTask);
        $this->fillTask($visibleTask, $request, $visibleProject);
        $visibleTask->coAssignees()->sync($request->coAssigneeUserIds());
        $taskChangeLogger->syncConversationAndLogChanges($beforeState, $visibleTask, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.projects.task_updated_success')]);

        return back();
    }

    public function updateWorkspaceTask(
        UpdateProjectTaskRequest $request,
        ProjectTask $projectTask,
        ProjectTaskChangeLogger $taskChangeLogger,
    ): RedirectResponse {
        $visibleTask = $this->visibleTask($request, $projectTask);
        abort_unless($request->user()->canManageTask($visibleTask), 403);

        $targetProject = $request->project();

        if ($this->isTasksMode($request)) {
            abort_if($targetProject !== null, 404);
        }

        if ($targetProject !== null) {
            $targetProject = $this->visibleProject($request, $targetProject);
            abort_unless($request->user()->canWorkOnProject($targetProject), 403);
        }

        $beforeState = $taskChangeLogger->snapshot($visibleTask);
        $this->fillTask($visibleTask, $request, $targetProject);
        $visibleTask->coAssignees()->sync($request->coAssigneeUserIds());
        $taskChangeLogger->syncConversationAndLogChanges($beforeState, $visibleTask, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.projects.task_updated_success')]);

        return $this->redirectForTask($request, $visibleTask->fresh(['project']));
    }

    public function moveWorkspaceTask(
        MoveProjectTaskRequest $request,
        ProjectTask $projectTask,
        ProjectTaskChangeLogger $taskChangeLogger,
    ): RedirectResponse {
        $visibleTask = $this->visibleTask($request, $projectTask);
        abort_unless($request->user()->canManageTask($visibleTask), 403);

        $beforeState = $taskChangeLogger->snapshot($visibleTask);
        $visibleTask->update([
            'status' => $request->status(),
            'completed_at' => $this->completedAtForStatus($visibleTask, $request->status()),
            'updated_by_user_id' => $request->user()->id,
        ]);
        $taskChangeLogger->syncConversationAndLogChanges($beforeState, $visibleTask, $request->user());

        return back();
    }

    public function storeTaskStage(StoreProjectTaskStageRequest $request): RedirectResponse
    {
        $name = (string) $request->validated('name');

        ProjectTaskStage::query()->create([
            'slug' => $this->uniqueTaskStageSlug($name),
            'name' => $name,
            'color' => (string) $request->validated('color'),
            'is_completed' => false,
            'sort_order' => $this->nextTaskStageSortOrder(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.projects.stage_created_success')]);

        return back();
    }

    public function updateTaskStage(
        UpdateProjectTaskStageRequest $request,
        ProjectTaskStage $projectTaskStage,
    ): RedirectResponse {
        $projectTaskStage->update([
            'name' => (string) $request->validated('name'),
            'color' => (string) $request->validated('color'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.projects.stage_updated_success')]);

        return back();
    }

    public function moveTaskStages(MoveProjectTaskStagesRequest $request): RedirectResponse
    {
        ProjectTaskStage::query()
            ->whereIn('id', $request->stageIds())
            ->get()
            ->keyBy('id')
            ->pipe(function ($stagesById) use ($request): void {
                foreach ($request->stageIds() as $index => $stageId) {
                    $stagesById[$stageId]?->update([
                        'sort_order' => $index,
                    ]);
                }
            });

        return back();
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

        $redirect = $this->isTasksMode($request)
            ? redirect()->route('tasks.index', ['view' => $this->taskDisplayMode($request)])
            : ($visibleTask->project_id !== null
                ? redirect()->route('projects.show', $visibleTask->project_id)
                : redirect()->route('projects.index'));

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
            'completed_at' => $this->completedAtForStatus(null, $request->validated('status')),
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
            'completed_at' => $this->completedAtForStatus($task, $request->validated('status')),
            'sort_order' => $request->validated('sort_order'),
            'updated_by_user_id' => $request->user()->id,
        ]);
    }

    private function redirectForTask(Request $request, ProjectTask $task): RedirectResponse
    {
        if ($this->isTasksMode($request) && $task->project_id === null) {
            return redirect()->route('tasks.show', [
                'projectTask' => $task,
                'view' => $this->taskDisplayMode($request),
            ]);
        }

        return redirect()->route('projects.workspace.tasks.show', $task);
    }

    private function isTasksMode(Request $request): bool
    {
        return $request->routeIs('tasks.*') || $request->string('mode')->value() === ProjectPageData::MODE_TASKS;
    }

    private function taskDisplayMode(Request $request): string
    {
        $taskDisplayMode = $request->string('view')->value();

        return in_array($taskDisplayMode, [
            ProjectPageData::VIEW_LIST,
            ProjectPageData::VIEW_KANBAN,
            ProjectPageData::VIEW_GANTT,
        ], true)
            ? $taskDisplayMode
            : ProjectPageData::VIEW_LIST;
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

    private function completedAtForStatus(?ProjectTask $task, string $status): ?CarbonInterface
    {
        if (! ProjectTaskStage::isCompletedSlug($status)) {
            return null;
        }

        return $task?->completed_at ?? now();
    }

    private function nextTaskStageSortOrder(): int
    {
        $completedStage = ProjectTaskStage::query()
            ->where('is_completed', true)
            ->ordered()
            ->first();

        if (! $completedStage) {
            return (int) ((ProjectTaskStage::query()->max('sort_order') ?? -1) + 1);
        }

        ProjectTaskStage::query()
            ->where('sort_order', '>=', $completedStage->sort_order)
            ->increment('sort_order');

        return $completedStage->sort_order;
    }

    private function uniqueTaskStageSlug(string $name): string
    {
        $baseSlug = Str::slug($name, '_');
        $slug = $baseSlug !== '' ? $baseSlug : 'stage';
        $suffix = 1;

        while (ProjectTaskStage::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug !== '' ? $baseSlug.'_'.$suffix : 'stage_'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * @return Collection<int, ProjectTask>
     */
    private function standaloneTasksForExport(User $user): Collection
    {
        return ProjectTask::query()
            ->visibleTo($user)
            ->whereNull('project_id')
            ->with([
                'assignee:id,email',
                'coAssignees:id,email',
            ])
            ->orderBy('sort_order')
            ->orderBy('due_at')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return Collection<int, ProjectTask>
     */
    private function projectTasksForExport(Request $request, Project $project): Collection
    {
        return ProjectTask::query()
            ->visibleTo($request->user())
            ->where('project_id', $project->id)
            ->with([
                'assignee:id,email',
                'coAssignees:id,email',
            ])
            ->orderBy('sort_order')
            ->orderBy('due_at')
            ->orderByDesc('created_at')
            ->get();
    }
}
