<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'bin' => fake()->unique()->numerify('############'),
            'contact_person' => fake()->name(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'currency' => 'KZT',
            'payment_terms_days' => fake()->randomElement([0, 15, 30, 45]),
            'lead_time_days' => fake()->numberBetween(1, 30),
            'rating' => fake()->randomFloat(2, 3, 5),
            'is_active' => true,
            'notes' => fake()->optional()->sentence(),
            'created_by_user_id' => User::factory(),
            'updated_by_user_id' => fn (array $attributes): mixed => $attributes['created_by_user_id'],
        ];
    }
}
