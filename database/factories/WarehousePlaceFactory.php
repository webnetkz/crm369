<?php

namespace Database\Factories;

use App\Models\WarehouseFloor;
use App\Models\WarehousePlace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarehousePlace>
 */
class WarehousePlaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_floor_id' => WarehouseFloor::factory(),
            'name' => 'Место '.fake()->unique()->numerify('###'),
            'qr_code' => null,
            'sort_order' => 1,
        ];
    }
}
