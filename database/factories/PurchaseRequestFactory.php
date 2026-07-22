<?php

namespace Database\Factories;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequest>
 */
class PurchaseRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'PR-'.fake()->unique()->numerify('######'),
            'title' => fake()->sentence(4),
            'status' => PurchaseRequest::STATUS_DRAFT,
            'needed_at' => fake()->dateTimeBetween('+1 week', '+2 months'),
            'budget_amount' => fake()->randomFloat(2, 10000, 1000000),
            'currency' => 'KZT',
            'justification' => fake()->sentence(),
            'requested_by_user_id' => User::factory(),
        ];
    }

    public function pendingApproval(): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseRequest::STATUS_PENDING_APPROVAL,
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => PurchaseRequest::STATUS_APPROVED,
            'submitted_at' => now()->subHour(),
            'approved_at' => now(),
            'approved_by_user_id' => User::factory(),
        ]);
    }
}
