<?php

use App\Models\EquipmentItem;
use App\Models\EquipmentItemHistory;
use App\Models\User;
use Database\Seeders\BulkEquipmentInventorySeeder;

test('bulk equipment inventory seeder creates 2000 devices and leaves 300 on balance', function () {
    User::factory()->count(5)->create();
    $inactiveUser = User::factory()->create([
        'is_active' => false,
        'deactivated_at' => now(),
    ]);

    $this->seed(BulkEquipmentInventorySeeder::class);

    expect(EquipmentItem::query()->count())->toBe(2000)
        ->and(EquipmentItem::query()->where('status', EquipmentItem::STATUS_ISSUED)->count())->toBe(1700)
        ->and(EquipmentItem::query()->where('status', EquipmentItem::STATUS_ON_BALANCE)->count())->toBe(300)
        ->and(
            EquipmentItem::query()
                ->where('status', EquipmentItem::STATUS_ON_BALANCE)
                ->whereNull('issued_to_user_id')
                ->whereNull('responsible_user_id')
                ->count()
        )->toBe(300)
        ->and(
            EquipmentItem::query()
                ->where('status', EquipmentItem::STATUS_ISSUED)
                ->whereNotNull('issued_to_user_id')
                ->whereNotNull('responsible_user_id')
                ->count()
        )->toBe(1700)
        ->and(EquipmentItem::query()->where('issued_to_user_id', $inactiveUser->id)->count())->toBe(0)
        ->and(EquipmentItemHistory::query()->count())->toBe(2000)
        ->and(
            EquipmentItem::query()
                ->where('status', EquipmentItem::STATUS_ISSUED)
                ->pluck('issued_to_user_id')
                ->filter()
                ->unique()
                ->count()
        )->toBe(5);
});
