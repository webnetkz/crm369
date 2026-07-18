<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\ChatMessageRead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessageRead>
 */
class ChatMessageReadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_message_id' => ChatMessage::factory(),
            'user_id' => User::factory(),
            'read_at' => now(),
        ];
    }
}
