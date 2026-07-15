<?php

namespace Database\Seeders;

use App\Models\EquipmentItem;
use App\Models\EquipmentItemHistory;
use Illuminate\Database\Seeder;

class EquipmentItemHistorySeeder extends Seeder
{
    public function run(): void
    {
        $equipmentItems = EquipmentItem::query()->take(3)->get();

        if ($equipmentItems->isEmpty()) {
            $equipmentItems = EquipmentItem::factory()->count(3)->create();
        }

        foreach ($equipmentItems as $equipmentItem) {
            EquipmentItemHistory::factory()->create([
                'equipment_item_id' => $equipmentItem->id,
            ]);
        }
    }
}
