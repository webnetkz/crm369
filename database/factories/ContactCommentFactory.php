<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\ContactComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactComment>
 */
class ContactCommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'content' => fake()->realTextBetween(40, 180),
            'created_by_user_id' => User::factory(),
        ];
    }
}
