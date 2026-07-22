<?php

namespace Database\Factories;

use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use App\Models\SupplierQuotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierQuotation>
 */
class SupplierQuotationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_request_item_id' => PurchaseRequestItem::factory(),
            'supplier_id' => Supplier::factory(),
            'unit_price' => fake()->randomFloat(2, 100, 50000),
            'currency' => 'KZT',
            'tax_percent' => fake()->randomElement([0, 12]),
            'delivery_cost' => fake()->randomFloat(2, 0, 10000),
            'quoted_at' => today(),
            'valid_until' => today()->addMonth(),
            'lead_time_days' => fake()->numberBetween(1, 30),
            'notes' => fake()->optional()->sentence(),
            'created_by_user_id' => User::factory(),
        ];
    }
}
