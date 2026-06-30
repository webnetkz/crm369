<?php

namespace Database\Factories;

use App\Models\PortalWebhook;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortalWebhook>
 */
class PortalWebhookFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = PortalWebhook::generatePlainTextToken();

        return [
            'name' => fake()->words(2, true),
            'token_prefix' => substr($token, 0, 12),
            'token_hash' => PortalWebhook::hashToken($token),
            'permissions' => [PortalWebhook::PERMISSION_PROJECTS_READ],
            'is_active' => true,
            'expires_at' => null,
            'last_used_at' => null,
            'created_by_user_id' => User::factory(),
        ];
    }
}
