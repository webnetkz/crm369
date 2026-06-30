<?php

namespace Database\Factories;

use App\Models\ApiAccessToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiAccessToken>
 */
class ApiAccessTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'token_prefix' => fake()->unique()->regexify('[A-Za-z0-9]{12}'),
            'token_hash' => hash('sha256', fake()->sha1()),
            'permissions' => [
                ApiAccessToken::PERMISSION_PROFILE_READ,
                ApiAccessToken::PERMISSION_NOTIFICATIONS_READ,
            ],
            'expires_at' => null,
            'last_used_at' => null,
            'last_used_ip_address' => null,
            'last_used_user_agent' => null,
        ];
    }
}
