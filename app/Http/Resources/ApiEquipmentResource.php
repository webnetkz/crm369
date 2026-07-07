<?php

namespace App\Http\Resources;

use App\Models\EquipmentItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EquipmentItem */
class ApiEquipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'qr_code' => $this->qr_code,
            'status' => $this->status,
            'status_label' => __(
                EquipmentItem::statusDefinitions()[$this->status]['label_key']
                    ?? 'ui.equipment.statuses.on_balance',
            ),
            'issued_to_user' => $this->serializeUser($this->issuedToUser),
            'responsible_user' => $this->serializeUser($this->responsibleUser),
            'created_by' => $this->serializeUser($this->createdByUser),
            'updated_by' => $this->serializeUser($this->updatedByUser),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array{id: int, name: string, last_name: string|null, email: string}|null
     */
    private function serializeUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'email' => $user->email,
        ];
    }
}
