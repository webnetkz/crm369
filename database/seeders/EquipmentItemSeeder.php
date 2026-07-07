<?php

namespace Database\Seeders;

use App\Models\EquipmentItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class EquipmentItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::query()->take(3)->get();

        if ($users->isEmpty()) {
            $users = User::factory()->count(3)->create();
        }

        $creator = $users->first();

        if (! $creator) {
            return;
        }

        EquipmentItem::factory()
            ->count(2)
            ->create([
                'created_by_user_id' => $creator->id,
                'updated_by_user_id' => $creator->id,
                'responsible_user_id' => $users->random()->id,
            ]);

        EquipmentItem::factory()
            ->issued()
            ->create([
                'created_by_user_id' => $creator->id,
                'updated_by_user_id' => $creator->id,
                'responsible_user_id' => $users->random()->id,
                'issued_to_user_id' => $users->last()?->id,
            ]);

        EquipmentItem::factory()
            ->maintenance()
            ->create([
                'created_by_user_id' => $creator->id,
                'updated_by_user_id' => $creator->id,
                'responsible_user_id' => $users->random()->id,
            ]);
    }
}
