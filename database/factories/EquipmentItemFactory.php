<?php

namespace Database\Factories;

use App\Models\EquipmentItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentItem>
 */
class EquipmentItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Ноутбук Lenovo ThinkPad',
                'Сканер Zebra',
                'Термопринтер Epson',
                'Планшет Samsung Galaxy Tab',
                'Моноблок HP ProOne',
            ]).' '.fake()->bothify('##'),
            'qr_code' => 'EQ-'.mb_strtoupper(fake()->bothify('??-####-??')),
            'status' => EquipmentItem::STATUS_ON_BALANCE,
            'issued_to_user_id' => null,
            'responsible_user_id' => User::factory(),
            'created_by_user_id' => User::factory(),
            'updated_by_user_id' => User::factory(),
        ];
    }

    public function issued(): static
    {
        return $this->state(fn (): array => [
            'status' => EquipmentItem::STATUS_ISSUED,
            'issued_to_user_id' => User::factory(),
        ]);
    }

    public function maintenance(): static
    {
        return $this->state(fn (): array => [
            'status' => EquipmentItem::STATUS_MAINTENANCE,
            'issued_to_user_id' => null,
        ]);
    }

    public function writtenOff(): static
    {
        return $this->state(fn (): array => [
            'status' => EquipmentItem::STATUS_WRITTEN_OFF,
            'issued_to_user_id' => null,
        ]);
    }
}
