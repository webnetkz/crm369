<?php

namespace Database\Factories;

use App\Models\Conference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Conference>
 */
class ConferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'room_name' => 'crm369-'.Str::lower((string) Str::ulid()),
            'public_token' => Str::random(40),
            'created_by_user_id' => User::factory(),
            'starts_at' => now()->addHour(),
            'ended_at' => null,
            'allow_external_guests' => true,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn (): array => [
            'ended_at' => now()->subMinutes(10),
        ]);
    }

    public function withoutExternalGuests(): static
    {
        return $this->state(fn (): array => [
            'allow_external_guests' => false,
        ]);
    }
}
