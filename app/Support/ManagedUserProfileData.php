<?php

namespace App\Support;

use App\Models\EquipmentItem;
use App\Models\User;
use Illuminate\Support\Collection;

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
            'issued_equipment' => $this->serializeIssuedEquipment($user),
            'group' => $group
                ? [
                    'id' => $group->id,
                    'name' => $group->name,
                    'display_name' => $group->displayName(),
                ]
                : null,
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
}
