<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'middle_name' => $user->middle_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'position' => $user->position,
            'avatar' => $user->avatar,
            'avatar_position_x' => $user->avatar_position_x,
            'avatar_position_y' => $user->avatar_position_y,
            'avatar_scale' => $user->avatar_scale,
            'language' => $user->resolvedLanguage(),
            'has_selected_language' => $user->hasSelectedLanguage(),
            'background_color' => $user->background_color,
            'background_image' => $user->background_image,
            'background_blur' => $user->background_blur,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'is_super_admin' => $user->isSuperAdmin(),
            'is_active' => $user->is_active,
            'deactivated_at' => $user->deactivated_at?->toISOString(),
            'group' => $user->isSuperAdmin()
                ? null
                : ($user->group
                ? [
                    'id' => $user->group->id,
                    'name' => $user->group->name,
                    'display_name' => $user->group->displayName(),
                ]
                : null),
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }
}
