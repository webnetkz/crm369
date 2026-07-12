<?php

namespace Database\Factories;

use App\Models\ReferenceDirectory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReferenceDirectory>
 */
class ReferenceDirectoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company().' directory';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'columns' => [
                [
                    'key' => 'title',
                    'label' => 'Title',
                    'type' => ReferenceDirectory::FIELD_TYPE_TEXT,
                    'is_required' => true,
                ],
                [
                    'key' => 'code',
                    'label' => 'Code',
                    'type' => ReferenceDirectory::FIELD_TYPE_NUMBER,
                    'is_required' => false,
                ],
            ],
        ];
    }
}
