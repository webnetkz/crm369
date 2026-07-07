<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Склад '.fake()->unique()->randomElement(['Север', 'Юг', 'Запад', 'Восток']),
            'area_sqm' => fake()->randomFloat(2, 150, 5000),
            'qr_code' => null,
            'created_by_user_id' => User::factory(),
            'updated_by_user_id' => null,
        ];
    }
}
