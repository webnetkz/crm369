<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessageAttachment>
 */
class ChatMessageAttachmentFactory extends Factory
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
            'original_name' => $this->faker->words(2, true).'.txt',
            'disk' => 'local',
            'path' => 'chat-attachments/'.$this->faker->uuid().'.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size_bytes' => $this->faker->numberBetween(128, 4096),
        ];
    }
}
