<?php

namespace App\Http\Resources;

use App\Models\MessengerIntegration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiMessengerIntegrationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MessengerIntegration $integration */
        $integration = $this->resource;

        return [
            'id' => $integration->id,
            'driver' => $integration->driver,
            'name' => $integration->name,
            'is_active' => $integration->is_active,
            'settings' => $integration->normalizedSettings(),
            'group_accesses' => $integration->relationLoaded('groupAccesses')
                ? $integration->groupAccesses->map(fn ($access): array => [
                    'user_group_id' => $access->user_group_id,
                    'access_level' => $access->access_level,
                ])->values()->all()
                : [],
            'created_at' => $integration->created_at?->toISOString(),
            'updated_at' => $integration->updated_at?->toISOString(),
        ];
    }
}
