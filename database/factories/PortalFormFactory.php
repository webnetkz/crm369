<?php

namespace Database\Factories;

use App\Models\PortalForm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PortalForm>
 */
class PortalFormFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_user_id' => User::factory(),
            'target_user_id' => User::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'submission_mode' => fake()->randomElement(PortalForm::availableSubmissionModes()),
            'public_token' => Str::lower(fake()->unique()->bothify('form########################')),
            'is_active' => true,
        ];
    }
}
