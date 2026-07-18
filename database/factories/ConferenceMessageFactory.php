<?php

namespace Database\Factories;

use App\Models\Conference;
use App\Models\ConferenceMessage;
use App\Models\ConferenceParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConferenceMessage>
 */
class ConferenceMessageFactory extends Factory
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
            'participant_id' => fn (array $attributes): int => ConferenceParticipant::factory()->create([
                'conference_id' => $attributes['conference_id'],
            ])->id,
            'display_name' => fake()->name(),
            'body' => fake()->sentence(),
        ];
    }
}
