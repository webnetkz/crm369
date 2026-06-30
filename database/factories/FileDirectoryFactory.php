<?php

namespace Database\Factories;

use App\Models\FileDirectory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FileDirectory>
 */
class FileDirectoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'owner_user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'sort_order' => 0,
        ];
    }
}
