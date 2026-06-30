<?php

namespace App\Support;

use App\Models\User;

class ManagedUserProfileData
{
    /**
     * @return array<string, mixed>
     */
    public function serialize(User $user): array
    {
        $group = $user->relationLoaded('group')
            ? $user->group
            : $user->group()->select(['id', 'name'])->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'avatar' => $user->avatar,
            'avatar_scale' => $user->avatar_scale,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
            'is_super_admin' => $user->isSuperAdmin(),
            'is_active' => $user->is_active,
            'deactivated_at' => $user->deactivated_at?->toISOString(),
            'group' => $group
                ? [
                    'id' => $group->id,
                    'name' => $group->name,
                    'display_name' => $group->displayName(),
                ]
                : null,
        ];
    }

    public function canEdit(?User $viewer, User $user): bool
    {
        if (! $viewer || ! $viewer->canManageUserAccounts()) {
            return false;
        }

        return ! $user->isSuperAdmin() || $viewer->isSuperAdmin();
    }
}
