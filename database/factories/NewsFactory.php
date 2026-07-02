<?php

namespace Database\Factories;

use App\Models\News;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<News>
 */
class NewsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(12),
            'content' => implode("\n\n", fake()->paragraphs(3)),
            'image_path' => null,
            'is_published' => true,
            'published_at' => now()->subDays(fake()->numberBetween(0, 14)),
            'created_by_user_id' => User::factory(),
            'updated_by_user_id' => User::factory(),
        ];
    }
}
