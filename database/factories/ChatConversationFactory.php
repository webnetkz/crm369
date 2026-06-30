<?php

namespace Database\Factories;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatConversation>
 */
class ChatConversationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ChatConversation::TYPE_DIRECT,
            'title' => null,
            'created_by_user_id' => User::factory(),
            'last_message_at' => null,
        ];
    }
}
