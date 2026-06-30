<?php

namespace Database\Factories;

use App\Models\CrmDeal;
use App\Models\CrmFunnel;
use App\Models\CrmFunnelStage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrmDeal>
 */
class CrmDealFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crm_funnel_id' => CrmFunnel::factory(),
            'crm_funnel_stage_id' => fn (array $attributes): int => CrmFunnelStage::factory()->create([
                'crm_funnel_id' => $attributes['crm_funnel_id'],
            ])->id,
            'responsible_user_id' => User::factory(),
            'created_by_user_id' => User::factory(),
            'updated_by_user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'company_name' => fake()->company(),
            'contact_name' => fake()->name(),
            'contact_phone' => fake()->phoneNumber(),
            'contact_email' => fake()->safeEmail(),
            'amount' => fake()->randomFloat(2, 1000, 250000),
            'currency' => 'USD',
            'expected_close_at' => fake()->dateTimeBetween('now', '+30 days'),
            'description' => fake()->sentence(),
            'custom_fields' => [
                'source' => fake()->randomElement(['Website', 'Call', 'Referral']),
            ],
            'sort_order' => 0,
        ];
    }
}
