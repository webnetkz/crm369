<?php

namespace App\Http\Resources;

use App\Models\ProjectTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiProjectTaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProjectTask $task */
        $task = $this->resource;

        return [
            'id' => $task->id,
            'project_id' => $task->project_id,
            'parent_task_id' => $task->parent_task_id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'importance' => $task->importance,
            'complexity' => $task->complexity,
            'due_at' => $task->due_at?->toISOString(),
            'completed_at' => $task->completed_at?->toISOString(),
            'sort_order' => $task->sort_order,
            'project' => $task->project
                ? [
                    'id' => $task->project->id,
                    'name' => $task->project->name,
                    'slug' => $task->project->slug,
                ]
                : null,
            'parent_task' => $task->parentTask
                ? [
                    'id' => $task->parentTask->id,
                    'title' => $task->parentTask->title,
                ]
                : null,
            'creator' => $task->creator
                ? (new ApiUserResource($task->creator))->resolve()
                : null,
            'assignee' => $task->assignee
                ? (new ApiUserResource($task->assignee))->resolve()
                : null,
            'co_assignees' => $task->relationLoaded('coAssignees')
                ? ApiUserResource::collection($task->coAssignees)->resolve()
                : [],
            'subtasks' => $task->relationLoaded('subtasks')
                ? ApiProjectTaskResource::collection($task->subtasks)->resolve()
                : [],
            'created_at' => $task->created_at?->toISOString(),
            'updated_at' => $task->updated_at?->toISOString(),
        ];
    }
}
