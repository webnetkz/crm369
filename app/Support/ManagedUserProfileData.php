<?php

namespace App\Support;

use App\Models\EquipmentItem;
use App\Models\User;
use Illuminate\Support\Collection;

class ManagedUserProfileData
{
    /**
     * @return array<int, array{id: int, name: string, last_name: string|null, middle_name: string|null, full_name: string, email: string, position: string|null, avatar: string|null, avatar_scale: float, is_active: bool}>
     */
    public function managerOptions(?User $viewer): array
    {
        if (! $viewer?->canManageUserAccounts()) {
            return [];
        }

        return User::query()
            ->select(['id', 'name', 'last_name', 'middle_name', 'email', 'position', 'avatar_path', 'avatar_scale', 'is_active'])
            ->orderBy('name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (User $user): array => $this->serializeStructureUser($user))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(User $user): array
    {
        $group = $user->relationLoaded('group')
            ? $user->group
            : $user->group()->select(['id', 'name'])->first();
        $manager = $user->relationLoaded('manager')
            ? $user->manager
            : $user->manager()->select(['id', 'name', 'last_name', 'middle_name', 'email', 'position', 'avatar_path', 'avatar_scale', 'is_active'])->first();
        /** @var Collection<int, User> $subordinates */
        $subordinates = $user->relationLoaded('subordinates')
            ? $user->subordinates
            : $user->subordinates()->select(['id', 'name', 'last_name', 'middle_name', 'email', 'position', 'avatar_path', 'avatar_scale', 'is_active', 'manager_id'])->get();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'middle_name' => $user->middle_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'position' => $user->position,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'avatar' => $user->avatar,
            'avatar_scale' => $user->avatar_scale,
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),
            'is_super_admin' => $user->isSuperAdmin(),
            'is_active' => $user->is_active,
            'deactivated_at' => $user->deactivated_at?->toISOString(),
            'issued_equipment' => $this->serializeIssuedEquipment($user),
            'group' => $group
                ? [
                    'id' => $group->id,
                    'name' => $group->name,
                    'display_name' => $group->displayName(),
                ]
                : null,
            'manager_id' => $manager?->id,
            'manager' => $manager ? $this->serializeStructureUser($manager) : null,
            'subordinates_count' => $subordinates->count(),
            'subordinates' => $subordinates
                ->map(fn (User $subordinate): array => $this->serializeStructureUser($subordinate))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function serializeIssuedEquipment(User $user): array
    {
        /** @var Collection<int, EquipmentItem> $equipmentItems */
        $equipmentItems = $user->relationLoaded('issuedEquipmentItems')
            ? $user->issuedEquipmentItems
            : $user->issuedEquipmentItems()
                ->with('responsibleUser:id,name,last_name,email')
                ->get();

        return $equipmentItems
            ->map(fn (EquipmentItem $equipmentItem): array => [
                'id' => $equipmentItem->id,
                'name' => $equipmentItem->name,
                'qr_code' => $equipmentItem->qr_code,
                'qr_code_svg_data_uri' => $equipmentItem->qrCodeSvgDataUri(),
                'status' => $equipmentItem->status,
                'status_label' => __(
                    EquipmentItem::statusDefinitions()[$equipmentItem->status]['label_key']
                        ?? 'ui.equipment.statuses.on_balance',
                ),
                'updated_at' => $equipmentItem->updated_at?->toISOString(),
                'responsible_user' => $equipmentItem->responsibleUser
                    ? [
                        'id' => $equipmentItem->responsibleUser->id,
                        'name' => $equipmentItem->responsibleUser->name,
                        'last_name' => $equipmentItem->responsibleUser->last_name,
                        'email' => $equipmentItem->responsibleUser->email,
                    ]
                    : null,
            ])
            ->values()
            ->all();
    }

    public function canEdit(?User $viewer, User $user): bool
    {
        if (! $viewer || ! $viewer->canManageUserAccounts()) {
            return false;
        }

        return ! $user->isSuperAdmin() || $viewer->isSuperAdmin();
    }

    /**
     * @return array{id: int, name: string, last_name: string|null, middle_name: string|null, full_name: string, email: string, position: string|null, avatar: string|null, avatar_scale: float, is_active: bool}
     */
    private function serializeStructureUser(User $user): array
    {
        $fullName = trim(implode(' ', array_filter([
            $user->name,
            $user->last_name,
            $user->middle_name,
        ])));

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'middle_name' => $user->middle_name,
            'full_name' => $fullName !== '' ? $fullName : $user->email,
            'email' => $user->email,
            'position' => $user->position,
            'avatar' => $user->avatar,
            'avatar_scale' => $user->avatar_scale,
            'is_active' => $user->is_active,
        ];
    }
}
