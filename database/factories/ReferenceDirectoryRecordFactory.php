<?php

namespace Database\Factories;

use App\Models\ReferenceDirectory;
use App\Models\ReferenceDirectoryRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferenceDirectoryRecord>
 */
class ReferenceDirectoryRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_directory_id' => ReferenceDirectory::factory(),
            'values' => [
                'title' => fake()->words(2, true),
                'code' => fake()->numberBetween(1, 999),
            ],
        ];
    }
}
