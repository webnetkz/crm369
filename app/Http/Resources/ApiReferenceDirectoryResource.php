<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiReferenceDirectoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $creatorName = trim(($this->creator?->name ?? '').' '.($this->creator?->last_name ?? ''));
        $updaterName = trim(($this->updater?->name ?? '').' '.($this->updater?->last_name ?? ''));

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'columns' => $this->columnDefinitions(),
            'records_count' => $this->records_count ?? ($this->relationLoaded('records') ? $this->records->count() : 0),
            'records' => $this->when(
                $this->relationLoaded('records'),
                fn (): array => ApiReferenceDirectoryRecordResource::collection($this->records)->resolve(),
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'creator' => $this->creator
                ? [
                    'id' => $this->creator->id,
                    'name' => $creatorName !== '' ? $creatorName : $this->creator->email,
                    'email' => $this->creator->email,
                ]
                : null,
            'updater' => $this->updater
                ? [
                    'id' => $this->updater->id,
                    'name' => $updaterName !== '' ? $updaterName : $this->updater->email,
                    'email' => $this->updater->email,
                ]
                : null,
        ];
    }
}
