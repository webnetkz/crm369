<?php

namespace Database\Factories;

use App\Models\MobileAccessToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MobileAccessToken>
 */
class MobileAccessTokenFactory extends Factory
{
    public function definition(): array
    {
        $tokenHash = hash('sha256', Str::random(96));

        return [
            'user_id' => User::factory(),
            'device_id' => fake()->uuid(),
            'token_prefix' => substr($tokenHash, 0, MobileAccessToken::TOKEN_PREFIX_LENGTH),
            'token_hash' => $tokenHash,
            'expires_at' => now()->addYear(),
            'last_used_at' => null,
            'last_used_ip_address' => null,
            'last_used_user_agent' => null,
        ];
    }
}
