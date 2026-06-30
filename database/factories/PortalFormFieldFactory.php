<?php

namespace Database\Factories;

use App\Models\PortalForm;
use App\Models\PortalFormField;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PortalFormField>
 */
class PortalFormFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'portal_form_id' => PortalForm::factory(),
            'key' => Str::snake(fake()->unique()->words(2, true)),
            'label' => fake()->sentence(2),
            'type' => fake()->randomElement(PortalFormField::availableTypes()),
            'placeholder' => fake()->optional()->sentence(3),
            'is_required' => fake()->boolean(70),
            'sort_order' => 10,
        ];
    }
}
