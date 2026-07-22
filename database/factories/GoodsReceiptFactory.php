<?php

namespace Database\Factories;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceipt>
 */
class GoodsReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'GR-'.fake()->unique()->numerify('######'),
            'purchase_order_id' => PurchaseOrder::factory()->sent(),
            'status' => GoodsReceipt::STATUS_POSTED,
            'received_at' => now(),
            'received_by_user_id' => User::factory(),
            'external_reference' => fake()->optional()->bothify('INV-####'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
