<?php

namespace Database\Factories;

use App\Models\MessengerIntegration;
use App\Models\MessengerIntegrationGroupAccess;
use App\Models\UserGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessengerIntegrationGroupAccess>
 */
class MessengerIntegrationGroupAccessFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'messenger_integration_id' => MessengerIntegration::factory(),
            'user_group_id' => UserGroup::factory(),
            'access_level' => fake()->randomElement(MessengerIntegrationGroupAccess::assignableAccessLevels()),
        ];
    }
}
