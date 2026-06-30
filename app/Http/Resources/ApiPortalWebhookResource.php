<?php

namespace App\Http\Resources;

use App\Models\PortalWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiPortalWebhookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PortalWebhook $webhook */
        $webhook = $this->resource;

        return [
            'id' => $webhook->id,
            'name' => $webhook->name,
            'token_prefix' => $webhook->token_prefix,
            'permissions' => $webhook->resolvedPermissions(),
            'is_active' => $webhook->is_active,
            'is_expired' => $webhook->isExpired(),
            'expires_at' => $webhook->expires_at?->toISOString(),
            'last_used_at' => $webhook->last_used_at?->toISOString(),
            'created_at' => $webhook->created_at?->toISOString(),
            'endpoint_url' => $webhook->endpointUrl(),
            'creator' => $webhook->creator
                ? (new ApiUserResource($webhook->creator))->resolve()
                : null,
        ];
    }
}
