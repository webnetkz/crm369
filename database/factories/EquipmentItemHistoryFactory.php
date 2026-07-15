<?php

namespace Database\Factories;

use App\Models\EquipmentItem;
use App\Models\EquipmentItemHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentItemHistory>
 */
class EquipmentItemHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $responsibleUser = [
            'id' => fake()->numberBetween(1000, 9999),
            'name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->safeEmail(),
        ];
        $issuedUser = [
            'id' => fake()->numberBetween(1000, 9999),
            'name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->safeEmail(),
        ];
        $equipmentName = 'Equipment '.fake()->bothify('##??');
        $qrCode = 'EQ-'.mb_strtoupper(fake()->bothify('??-####-??'));

        return [
            'equipment_item_id' => EquipmentItem::factory(),
            'event_type' => EquipmentItemHistory::EVENT_CREATED,
            'source' => fake()->randomElement(EquipmentItemHistory::availableSources()),
            'actor_user_id' => User::factory(),
            'changes' => [
                'name' => [
                    'from' => null,
                    'to' => $equipmentName,
                ],
                'status' => [
                    'from' => null,
                    'to' => EquipmentItem::STATUS_ON_BALANCE,
                ],
                'responsible_user' => [
                    'from' => null,
                    'to' => $responsibleUser,
                ],
            ],
            'snapshot' => [
                'name' => $equipmentName,
                'qr_code' => $qrCode,
                'status' => EquipmentItem::STATUS_ISSUED,
                'responsible_user' => $responsibleUser,
                'issued_to_user' => $issuedUser,
            ],
            'changed_at' => now()->subMinutes(fake()->numberBetween(1, 240)),
        ];
    }
}
