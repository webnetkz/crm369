<?php

namespace Database\Factories;

use App\Models\PortalSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortalSetting>
 */
class PortalSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_name' => fake()->company(),
            'logo_path' => null,
            'default_language' => 'ru',
        ];
    }
}
