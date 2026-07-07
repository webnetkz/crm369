<?php

namespace Database\Factories;

use App\Models\WarehouseColumn;
use App\Models\WarehouseFloor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarehouseFloor>
 */
class WarehouseFloorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_column_id' => WarehouseColumn::factory(),
            'name' => 'Этаж '.fake()->unique()->numerify('#'),
            'qr_code' => null,
            'sort_order' => 1,
        ];
    }
}
