<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'item_name' => fake()->words(3, true),
            'sku' => strtoupper(fake()->bothify('SKU-####??')),
            'unit' => 'pcs',
            'quantity' => fake()->numberBetween(1, 50),
            'received_quantity' => 0,
            'returned_quantity' => 0,
            'unit_price' => fake()->randomFloat(2, 100, 50000),
            'tax_percent' => 12,
            'line_total' => fake()->randomFloat(2, 1000, 100000),
        ];
    }
}
