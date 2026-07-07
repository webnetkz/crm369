<?php

namespace App\Http\Requests;

use App\Models\EquipmentItem;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateEquipmentItemRequest extends StoreEquipmentItemRequest
{
    protected function qrCodeUniqueRule(): Unique
    {
        /** @var EquipmentItem|null $equipmentItem */
        $equipmentItem = $this->route('equipmentItem');

        return Rule::unique('equipment_items', 'qr_code')->ignore($equipmentItem?->id);
    }
}
