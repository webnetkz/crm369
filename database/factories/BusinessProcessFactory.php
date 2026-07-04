<?php

namespace Database\Factories;

use App\Models\BusinessProcess;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BusinessProcess>
 */
class BusinessProcessFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by_user_id' => User::factory(),
            'updated_by_user_id' => User::factory(),
            'name' => fake()->unique()->sentence(3),
            'slug' => Str::slug(fake()->unique()->sentence(3)).'-'.fake()->unique()->numberBetween(10, 999),
            'description' => fake()->sentence(10),
            'trigger_type' => BusinessProcess::TRIGGER_TYPE_DOMAIN_EVENT,
            'trigger_event' => 'contacts.created',
            'is_active' => true,
            'version' => 1,
            'last_published_at' => now(),
            'definition' => BusinessProcess::defaultDefinition(),
        ];
    }
}
