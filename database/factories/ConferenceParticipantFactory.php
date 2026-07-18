<?php

namespace Database\Factories;

use App\Models\Conference;
use App\Models\ConferenceParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ConferenceParticipant>
 */
class ConferenceParticipantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = Str::random(64);

        return [
            'conference_id' => Conference::factory(),
            'user_id' => User::factory(),
            'display_name' => fake()->name(),
            'access_token_hash' => hash('sha256', $token),
            'is_guest' => false,
            'joined_at' => now(),
            'last_seen_at' => now(),
            'left_at' => null,
        ];
    }
}
