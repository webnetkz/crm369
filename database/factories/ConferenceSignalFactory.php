<?php

namespace Database\Factories;

use App\Models\Conference;
use App\Models\ConferenceParticipant;
use App\Models\ConferenceSignal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConferenceSignal>
 */
class ConferenceSignalFactory extends Factory
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
            'sender_participant_id' => fn (array $attributes): int => ConferenceParticipant::factory()->create([
                'conference_id' => $attributes['conference_id'],
            ])->id,
            'recipient_participant_id' => fn (array $attributes): int => ConferenceParticipant::factory()->create([
                'conference_id' => $attributes['conference_id'],
            ])->id,
            'type' => 'ice-candidate',
            'payload' => ['candidate' => fake()->uuid()],
            'expires_at' => now()->addMinutes(2),
        ];
    }
}
