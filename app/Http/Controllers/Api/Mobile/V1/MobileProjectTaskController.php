<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MoveProjectTaskRequest;
use App\Http\Requests\MoveProjectTaskStagesRequest;
use App\Http\Requests\StoreProjectTaskStageRequest;
use App\Http\Requests\UpdateProjectTaskStageRequest;
use App\Http\Resources\ApiProjectTaskResource;
use App\Models\ProjectTask;
use App\Models\ProjectTaskStage;
use App\Support\ProjectPageData;
use App\Support\ProjectTaskBoardManager;
use App\Support\TaskConversationData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileProjectTaskController extends Controller
{
    /**
     * The standalone tasks page (the web tasks page props). An explicit `view` is remembered as the user's preference.
     */
    public function index(Request $request, ProjectPageData $pageData, ProjectTaskBoardManager $boardManager): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $pageData->build(
                $user,
                mode: ProjectPageData::MODE_TASKS,
                taskDisplayMode: $boardManager->taskDisplayMode($user, $request->string('view')->value()),
            ),
        ]);
    }

    /**
     * The projects workspace (the web projects page props): projects, task groups, stages and options.
     */
    public function workspace(Request $request, ProjectPageData $pageData): JsonResponse
    {
        return response()->json([
            'data' => $pageData->build($request->user()),
        ]);
    }

    /**
     * Move a task to another kanban stage.
     */
    public function move(
        MoveProjectTaskRequest $request,
        ProjectTask $projectTask,
        ProjectTaskBoardManager $boardManager,
    ): JsonResponse {
        $visibleTask = $this->visibleTask($request, $projectTask);
        abort_unless($request->user()->canManageTask($visibleTask), 403);

        $boardManager->moveTask($visibleTask, $request->status(), $request->user());

        return response()->json([
            'message' => __('ui.projects.task_updated_success'),
            'data' => $this->task($visibleTask),
        ]);
    }

    /**
     * The task discussion. Messages are posted through the chat messages endpoint of the conversation.
     */
    public function conversation(
        Request $request,
        ProjectTask $projectTask,
        TaskConversationData $taskConversationData,
    ): JsonResponse {
        return response()->json([
            'data' => [
                'conversation' => $taskConversationData->open($projectTask, $request->user()),
            ],
        ]);
    }

    public function storeStage(
        StoreProjectTaskStageRequest $request,
        ProjectTaskBoardManager $boardManager,
        ProjectPageData $pageData,
    ): JsonResponse {
        $stage = $boardManager->createStage(
            (string) $request->validated('name'),
            (string) $request->validated('color'),
        );

        return response()->json([
            'message' => __('ui.projects.stage_created_success'),
            'data' => $pageData->taskStatusOption($stage->refresh()),
        ], 201);
    }

    /**
     * Reorder the kanban stages. The full list of stage ids is required.
     */
    public function moveStages(
        MoveProjectTaskStagesRequest $request,
        ProjectTaskBoardManager $boardManager,
        ProjectPageData $pageData,
    ): JsonResponse {
        $boardManager->reorderStages($request->stageIds());

        return response()->json([
            'message' => __('ui.projects.stage_updated_success'),
            'data' => $pageData->taskStatusOptions(),
        ]);
    }

    public function updateStage(
        UpdateProjectTaskStageRequest $request,
        ProjectTaskStage $projectTaskStage,
        ProjectPageData $pageData,
    ): JsonResponse {
        $projectTaskStage->update([
            'name' => (string) $request->validated('name'),
            'color' => (string) $request->validated('color'),
        ]);

        return response()->json([
            'message' => __('ui.projects.stage_updated_success'),
            'data' => $pageData->taskStatusOption($projectTaskStage->refresh()),
        ]);
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

    /**
     * The task in the same shape as the task endpoints of the API (store, update).
     *
     * @return array<string, mixed>
     */
    private function task(ProjectTask $task): array
    {
        $freshTask = $task->fresh() ?? $task;

        return (new ApiProjectTaskResource($freshTask->load([
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
        ])))->resolve();
    }
}
