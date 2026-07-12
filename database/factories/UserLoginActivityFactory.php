<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserLoginActivity;
use App\Support\UserAgentDetails;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserLoginActivity>
 */
class UserLoginActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
        $device = UserAgentDetails::from($userAgent);

        return [
            'user_id' => User::factory(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => $userAgent,
            'browser' => $device->browser,
            'platform' => $device->platform,
            'device_type' => $device->deviceType,
            'device_signature' => $device->signature,
            'is_new_device' => false,
            'is_new_ip' => false,
            'logged_in_at' => now(),
        ];
    }
}
