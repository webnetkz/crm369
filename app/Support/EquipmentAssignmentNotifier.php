<?php

namespace App\Support;

use App\Models\EquipmentItem;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Lang;

class EquipmentAssignmentNotifier
{
    public function sendForAssignmentChanges(
        EquipmentItem $equipmentItem,
        ?int $previousIssuedToUserId = null,
        ?int $previousResponsibleUserId = null,
    ): void {
        $equipmentItem->load([
            'issuedToUser:id,name,last_name,email,language,has_selected_language',
            'responsibleUser:id,name,last_name,email,language,has_selected_language',
        ]);

        $issuedToUser = $equipmentItem->issuedToUser;

        if ($issuedToUser instanceof User && $issuedToUser->id !== $previousIssuedToUserId) {
            $this->sendNotification(
                $issuedToUser,
                $equipmentItem,
                'ui.notifications.equipment_issued_title',
                'ui.notifications.equipment_issued_message',
            );
        }

        $responsibleUser = $equipmentItem->responsibleUser;

        if ($responsibleUser instanceof User && $responsibleUser->id !== $previousResponsibleUserId) {
            $this->sendNotification(
                $responsibleUser,
                $equipmentItem,
                'ui.notifications.equipment_responsible_title',
                'ui.notifications.equipment_responsible_message',
            );
        }
    }

    private function sendNotification(
        User $user,
        EquipmentItem $equipmentItem,
        string $titleKey,
        string $messageKey,
    ): void {
        $locale = $user->resolvedLanguage();

        $user->notify(new SystemNotification(
            title: Lang::get($titleKey, [], $locale),
            message: Lang::get($messageKey, [
                'name' => $equipmentItem->name,
                'qr_code' => $equipmentItem->qr_code,
            ], $locale),
            actionUrl: route('equipment.index', [
                'equipment' => $equipmentItem->id,
                'dialog' => 'details',
            ]),
            actionLabel: Lang::get('ui.notifications.open_target', [], $locale),
        ));
    }
}
