<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseReturn>
 */
class PurchaseReturnFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'RT-'.fake()->unique()->numerify('######'),
            'purchase_order_id' => PurchaseOrder::factory()->sent(),
            'status' => PurchaseReturn::STATUS_POSTED,
            'returned_at' => now(),
            'created_by_user_id' => User::factory(),
            'reason' => fake()->sentence(),
            'total_amount' => fake()->randomFloat(2, 1000, 100000),
        ];
    }
}
