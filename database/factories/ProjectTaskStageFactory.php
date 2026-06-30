<?php

namespace Database\Factories;

use App\Models\ProjectTaskStage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProjectTaskStage>
 */
class ProjectTaskStageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name),
            'name' => Str::title($name),
            'color' => fake()->hexColor(),
            'is_completed' => false,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'is_completed' => true,
        ]);
    }
}
