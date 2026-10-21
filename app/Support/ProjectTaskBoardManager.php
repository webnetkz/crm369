<?php

namespace App\Support;

use App\Models\ProjectTask;
use App\Models\ProjectTaskStage;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProjectTaskBoardManager
{
    public function __construct(private ProjectTaskChangeLogger $taskChangeLogger) {}

    /**
     * The view (list, kanban or gantt) of the standalone tasks page. An explicitly requested view is
     * remembered as the user's preference, otherwise the saved preference or the list view is used.
     */
    public function taskDisplayMode(?User $user, ?string $requestedTaskDisplayMode): string
    {
        if (is_string($requestedTaskDisplayMode) && $this->isValidTaskDisplayMode($requestedTaskDisplayMode)) {
            $this->persistTaskDisplayMode($user, $requestedTaskDisplayMode);

            return $requestedTaskDisplayMode;
        }

        if (! $this->canPersistTaskDisplayMode()) {
            return ProjectPageData::VIEW_LIST;
        }

        $preferredTaskDisplayMode = $user?->task_display_mode;

        return is_string($preferredTaskDisplayMode) && $this->isValidTaskDisplayMode($preferredTaskDisplayMode)
            ? $preferredTaskDisplayMode
            : ProjectPageData::VIEW_LIST;
    }

    /**
     * Create a kanban stage placed right before the first completed stage.
     */
    public function createStage(string $name, string $color): ProjectTaskStage
    {
        return ProjectTaskStage::query()->create([
            'slug' => $this->uniqueStageSlug($name),
            'name' => $name,
            'color' => $color,
            'is_completed' => false,
            'sort_order' => $this->nextStageSortOrder(),
        ]);
    }

    /**
     * Store the board order of the stages: the position in the list becomes the stage sort order.
     *
     * @param  array<int, int>  $stageIds
     */
    public function reorderStages(array $stageIds): void
    {
        ProjectTaskStage::query()
            ->whereIn('id', $stageIds)
            ->get()
            ->keyBy('id')
            ->pipe(function ($stagesById) use ($stageIds): void {
                foreach ($stageIds as $index => $stageId) {
                    $stagesById[$stageId]?->update([
                        'sort_order' => $index,
                    ]);
                }
            });
    }

    /**
     * Move a task to another kanban stage and write the change into the task discussion.
     */
    public function moveTask(ProjectTask $task, string $status, User $actor): void
    {
        $beforeState = $this->taskChangeLogger->snapshot($task);

        $task->update([
            'status' => $status,
            'completed_at' => $this->completedAtForStatus($task, $status),
            'updated_by_user_id' => $actor->id,
        ]);

        $this->taskChangeLogger->syncConversationAndLogChanges($beforeState, $task, $actor);
    }

    /**
     * Tasks in a completed stage keep their original completion time, other tasks are not completed.
     */
    public function completedAtForStatus(?ProjectTask $task, string $status): ?CarbonInterface
    {
        if (! ProjectTaskStage::isCompletedSlug($status)) {
            return null;
        }

        return $task->completed_at ?? now();
    }

    private function isValidTaskDisplayMode(string $taskDisplayMode): bool
    {
        return in_array($taskDisplayMode, [
            ProjectPageData::VIEW_LIST,
            ProjectPageData::VIEW_KANBAN,
            ProjectPageData::VIEW_GANTT,
        ], true);
    }

    private function persistTaskDisplayMode(?User $user, string $taskDisplayMode): void
    {
        if (
            $user === null
            || ! $this->canPersistTaskDisplayMode()
            || $user->task_display_mode === $taskDisplayMode
        ) {
            return;
        }

        $user->forceFill([
            'task_display_mode' => $taskDisplayMode,
        ])->save();
    }

    private function canPersistTaskDisplayMode(): bool
    {
        return Schema::hasColumn('users', 'task_display_mode');
    }

    private function nextStageSortOrder(): int
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

    private function uniqueStageSlug(string $name): string
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
}
