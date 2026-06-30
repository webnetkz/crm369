<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(Contact::availableTypes());

        return [
            'type' => $type,
            'name' => $type === Contact::TYPE_PERSON
                ? fake()->name()
                : fake()->company(),
            'contact_person' => $type === Contact::TYPE_COMPANY
                ? fake()->name()
                : null,
            'position' => fake()->jobTitle(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'notes' => fake()->optional()->sentence(),
            'company_requisites' => $type === Contact::TYPE_COMPANY
                ? [
                    'bin' => fake()->numerify('############'),
                    'legal_address' => fake()->address(),
                    'actual_address' => fake()->address(),
                    'bank_name' => fake()->company().' Bank',
                    'bank_bik' => fake()->bothify('ABKZKZKX'),
                    'iban' => 'KZ'.fake()->numerify('##################'),
                    'kbe' => fake()->randomElement(['17', '18']),
                ]
                : null,
            'created_by_user_id' => User::factory(),
            'updated_by_user_id' => User::factory(),
        ];
    }

    public function person(): static
    {
        return $this->state(fn (): array => [
            'type' => Contact::TYPE_PERSON,
            'name' => fake()->name(),
            'contact_person' => null,
            'company_requisites' => null,
        ]);
    }

    public function company(): static
    {
        return $this->state(fn (): array => [
            'type' => Contact::TYPE_COMPANY,
            'name' => fake()->company(),
            'contact_person' => fake()->name(),
            'company_requisites' => [
                'bin' => fake()->numerify('############'),
                'legal_address' => fake()->address(),
                'actual_address' => fake()->address(),
                'bank_name' => fake()->company().' Bank',
                'bank_bik' => fake()->bothify('ABKZKZKX'),
                'iban' => 'KZ'.fake()->numerify('##################'),
                'kbe' => fake()->randomElement(['17', '18']),
            ],
        ]);
    }
}
