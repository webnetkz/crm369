<?php

namespace Database\Factories;

use App\Models\MobileDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MobileDevice>
 */
class MobileDeviceFactory extends Factory
{
    public function definition(): array
    {
        $fcmToken = fake()->sha256().':'.fake()->sha256();

        return [
            'user_id' => User::factory(),
            'device_id' => fake()->uuid(),
            'platform' => 'android',
            'name' => fake()->randomElement(['Pixel 9', 'Galaxy S25', 'Android device']),
            'app_version' => '2.0.0',
            'fcm_token' => $fcmToken,
            'fcm_token_hash' => hash('sha256', $fcmToken),
            'last_seen_at' => now(),
            'disabled_at' => null,
        ];
    }
}
