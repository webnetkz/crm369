<?php

namespace App\Support;

use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\User;

class GeneralChatManager
{
    public function ensureConversation(): ChatConversation
    {
        $conversation = ChatConversation::query()
            ->general()
            ->orderBy('id')
            ->first();

        if ($conversation === null) {
            $conversation = new ChatConversation([
                'type' => ChatConversation::TYPE_GENERAL,
                'title' => ChatConversation::GENERAL_CHAT_TITLE,
                'created_by_user_id' => null,
            ]);
        }

        $conversation->forceFill([
            'type' => ChatConversation::TYPE_GENERAL,
            'title' => ChatConversation::GENERAL_CHAT_TITLE,
        ]);

        if (ChatConversation::supportsSystemKey()) {
            $conversation->system_key = ChatConversation::SYSTEM_KEY_GENERAL;
        }

        if ($conversation->isDirty(['type', 'system_key', 'title'])) {
            $conversation->save();
        }

        return $conversation;
    }

    public function ensureForUser(User $user): ChatConversation
    {
        $conversation = $this->ensureConversation();

        $this->ensureParticipant($conversation, $user);

        return $conversation;
    }

    public function ensureParticipant(ChatConversation $conversation, User $user): void
    {
        if (! $conversation->isGeneralConversation()) {
            return;
        }

        ChatConversationParticipant::query()->firstOrCreate(
            [
                'chat_conversation_id' => $conversation->id,
                'user_id' => $user->id,
            ],
            [
                'last_read_at' => $conversation->last_message_at,
            ],
        );
    }
}
