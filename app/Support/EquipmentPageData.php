<?php

namespace App\Support;

use App\Models\EquipmentItem;
use App\Models\User;

class EquipmentPageData
{
    /**
     * @return array<string, mixed>
     */
    public function build(User $viewer): array
    {
        $equipmentItems = EquipmentItem::query()
            ->with([
                'issuedToUser:id,name,last_name,email',
                'responsibleUser:id,name,last_name,email',
            ])
            ->ordered()
            ->get();

        return [
            'equipmentItems' => $equipmentItems
                ->map(fn (EquipmentItem $equipmentItem): array => [
                    'id' => $equipmentItem->id,
                    'name' => $equipmentItem->name,
                    'qr_code' => $equipmentItem->qr_code,
                    'status' => $equipmentItem->status,
                    'status_label' => __(
                        EquipmentItem::statusDefinitions()[$equipmentItem->status]['label_key']
                            ?? 'ui.equipment.statuses.on_balance'
                    ),
                    'issued_to_user' => $equipmentItem->issuedToUser
                        ? [
                            'id' => $equipmentItem->issuedToUser->id,
                            'name' => $equipmentItem->issuedToUser->name,
                            'last_name' => $equipmentItem->issuedToUser->last_name,
                            'email' => $equipmentItem->issuedToUser->email,
                        ]
                        : null,
                    'responsible_user' => $equipmentItem->responsibleUser
                        ? [
                            'id' => $equipmentItem->responsibleUser->id,
                            'name' => $equipmentItem->responsibleUser->name,
                            'last_name' => $equipmentItem->responsibleUser->last_name,
                            'email' => $equipmentItem->responsibleUser->email,
                        ]
                        : null,
                    'updated_at' => $equipmentItem->updated_at?->toISOString(),
                ])
                ->values()
                ->all(),
            'availableUsers' => User::query()
                ->select(['id', 'name', 'last_name', 'email'])
                ->where('is_active', true)
                ->orderBy('name')
                ->orderBy('last_name')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                ])
                ->values()
                ->all(),
            'statusOptions' => collect(EquipmentItem::statusDefinitions())
                ->map(fn (array $definition, string $status): array => [
                    'value' => $status,
                    'label' => __($definition['label_key']),
                    'description' => __($definition['description_key']),
                ])
                ->values()
                ->all(),
            'stats' => [
                'total' => $equipmentItems->count(),
                'on_balance' => $equipmentItems->where('status', EquipmentItem::STATUS_ON_BALANCE)->count(),
                'issued' => $equipmentItems->where('status', EquipmentItem::STATUS_ISSUED)->count(),
                'maintenance' => $equipmentItems
                    ->filter(fn (EquipmentItem $equipmentItem): bool => in_array(
                        $equipmentItem->status,
                        [EquipmentItem::STATUS_MAINTENANCE, EquipmentItem::STATUS_REPAIR],
                        true,
                    ))
                    ->count(),
                'written_off' => $equipmentItems->where('status', EquipmentItem::STATUS_WRITTEN_OFF)->count(),
            ],
            'viewer' => [
                'id' => $viewer->id,
            ],
        ];
    }
}
