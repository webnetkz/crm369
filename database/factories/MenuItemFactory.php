<?php

namespace Database\Factories;

use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => null,
            'type' => MenuItem::TYPE_CUSTOM,
            'user_id' => null,
            'is_global' => false,
            'title' => fake()->words(2, true),
            'icon' => null,
            'url' => fake()->url(),
            'opens_in_new_tab' => false,
            'is_visible' => true,
            'sort_order' => fake()->numberBetween(200, 500),
        ];
    }
}
