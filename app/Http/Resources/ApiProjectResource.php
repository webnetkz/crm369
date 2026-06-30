<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Project $project */
        $project = $this->resource;

        return [
            'id' => $project->id,
            'name' => $project->name,
            'slug' => $project->slug,
            'description' => $project->description,
            'is_archived' => $project->is_archived,
            'owner' => $project->owner
                ? (new ApiUserResource($project->owner))->resolve()
                : null,
            'members' => $project->relationLoaded('members')
                ? ApiUserResource::collection($project->members)->resolve()
                : [],
            'tasks' => $project->relationLoaded('tasks')
                ? ApiProjectTaskResource::collection($project->tasks)->resolve()
                : [],
            'members_count' => $project->members_count,
            'tasks_count' => $project->tasks_count,
            'open_tasks_count' => $project->open_tasks_count,
            'completed_tasks_count' => $project->completed_tasks_count,
            'created_at' => $project->created_at?->toISOString(),
            'updated_at' => $project->updated_at?->toISOString(),
        ];
    }
}
