<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'PO-'.fake()->unique()->numerify('######'),
            'purchase_request_id' => PurchaseRequest::factory()->approved(),
            'supplier_id' => Supplier::factory(),
            'status' => PurchaseOrder::STATUS_DRAFT,
            'currency' => 'KZT',
            'ordered_at' => today(),
            'expected_at' => today()->addWeeks(2),
            'subtotal' => 0,
            'tax_amount' => 0,
            'delivery_amount' => 0,
            'total_amount' => 0,
            'notes' => fake()->optional()->sentence(),
            'created_by_user_id' => User::factory(),
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseOrder::STATUS_SENT,
            'sent_at' => now(),
            'sent_by_user_id' => User::factory(),
        ]);
    }
}
