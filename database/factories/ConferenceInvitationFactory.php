<?php

namespace Database\Factories;

use App\Models\Conference;
use App\Models\ConferenceInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConferenceInvitation>
 */
class ConferenceInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conference_id' => Conference::factory(),
            'user_id' => User::factory(),
            'invited_by_user_id' => User::factory(),
            'joined_at' => null,
            'last_opened_at' => null,
        ];
    }
}
