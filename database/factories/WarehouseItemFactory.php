<?php

namespace Database\Factories;

use App\Models\WarehouseItem;
use App\Models\WarehousePlace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarehouseItem>
 */
class WarehouseItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_place_id' => WarehousePlace::factory(),
            'name' => fake()->randomElement([
                'Подшипник SKF 6204',
                'Электродвигатель 5 кВт',
                'Редуктор планетарный',
                'Катушка кабеля ВВГ',
                'Коробка крепежа M8',
            ]),
            'sku' => fake()->bothify('SKU-####-??'),
            'qr_code' => 'WI-'.mb_strtoupper((string) fake()->bothify('??-####-??')),
            'quantity' => fake()->numberBetween(1, 12),
            'notes' => fake()->boolean(35) ? fake()->sentence() : null,
        ];
    }

    public function forPlace(WarehousePlace $place): static
    {
        return $this->state(fn (): array => [
            'warehouse_place_id' => $place->id,
        ]);
    }
}
