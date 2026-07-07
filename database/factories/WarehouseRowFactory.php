<?php

namespace Database\Factories;

use App\Models\Warehouse;
use App\Models\WarehouseRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarehouseRow>
 */
class WarehouseRowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'name' => 'Ряд '.fake()->unique()->lexify('?'),
            'qr_code' => null,
            'sort_order' => 1,
        ];
    }
}
