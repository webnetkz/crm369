<?php

namespace Database\Factories;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequestItem>
 */
class PurchaseRequestItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_request_id' => PurchaseRequest::factory(),
            'item_name' => fake()->words(3, true),
            'sku' => strtoupper(fake()->bothify('SKU-####??')),
            'unit' => 'pcs',
            'quantity' => fake()->numberBetween(1, 50),
            'target_unit_price' => fake()->randomFloat(2, 100, 50000),
            'production_reference' => fake()->optional()->bothify('MO-####'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
