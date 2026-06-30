<?php

namespace Database\Factories;

use App\Models\CrmFunnel;
use App\Models\CrmFunnelStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrmFunnelStage>
 */
class CrmFunnelStageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crm_funnel_id' => CrmFunnel::factory(),
            'name' => fake()->words(2, true),
            'color' => fake()->hexColor(),
            'type' => CrmFunnelStage::TYPE_OPEN,
            'sort_order' => 0,
        ];
    }
}
