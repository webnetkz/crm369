<?php

namespace Database\Factories;

use App\Models\SystemSecuritySetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemSecuritySetting>
 */
class SystemSecuritySettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requires_two_factor_authentication' => false,
            'enforced_at' => null,
            'updated_by_user_id' => null,
        ];
    }
}
