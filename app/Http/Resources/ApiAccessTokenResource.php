<?php

namespace App\Http\Resources;

use App\Models\ApiAccessToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiAccessTokenResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ApiAccessToken $token */
        $token = $this->resource;

        return [
            'id' => $token->id,
            'name' => $token->name,
            'token_prefix' => $token->token_prefix,
            'permissions' => $token->resolvedPermissions(),
            'expires_at' => $token->expires_at?->toISOString(),
            'is_expired' => $token->isExpired(),
            'last_used_at' => $token->last_used_at?->toISOString(),
            'last_used_ip_address' => $token->last_used_ip_address,
            'last_used_user_agent' => $token->last_used_user_agent,
            'created_at' => $token->created_at?->toISOString(),
        ];
    }
}
