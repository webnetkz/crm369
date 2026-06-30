<?php

namespace Database\Factories;

use App\Models\PortalForm;
use App\Models\PortalFormSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortalFormSubmission>
 */
class PortalFormSubmissionFactory extends Factory
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
            'target_user_id' => User::factory(),
            'payload' => [
                [
                    'field_id' => 1,
                    'key' => 'message',
                    'label' => 'Message',
                    'type' => 'textarea',
                    'value' => fake()->paragraph(),
                ],
            ],
            'project_task_id' => null,
            'chat_conversation_id' => null,
            'chat_message_id' => null,
        ];
    }
}
