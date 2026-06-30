<?php

namespace Database\Factories;

use App\Models\CrmFunnel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CrmFunnel>
 */
class CrmFunnelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->sentence(2);
        $user = User::factory();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'color' => fake()->hexColor(),
            'is_active' => true,
            'deal_fields' => [
                [
                    'key' => 'source',
                    'label' => 'Source',
                    'type' => CrmFunnel::FIELD_TYPE_TEXT,
                    'is_required' => false,
                ],
            ],
            'created_by_user_id' => $user,
            'updated_by_user_id' => $user,
        ];
    }
}
