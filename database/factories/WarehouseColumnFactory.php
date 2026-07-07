<?php

namespace Database\Factories;

use App\Models\WarehouseColumn;
use App\Models\WarehouseRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarehouseColumn>
 */
class WarehouseColumnFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_row_id' => WarehouseRow::factory(),
            'name' => 'Колонка '.fake()->unique()->numerify('##'),
            'qr_code' => null,
            'sort_order' => 1,
        ];
    }
}
