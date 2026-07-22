<?php

namespace App\Support;

use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

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

    public function ensureActiveParticipants(ChatConversation $conversation): void
    {
        if (! $conversation->isGeneralConversation()) {
            return;
        }

        $createdAt = now();

        User::query()
            ->select('id')
            ->where('is_active', true)
            ->whereNotIn(
                'id',
                ChatConversationParticipant::query()
                    ->select('user_id')
                    ->where('chat_conversation_id', $conversation->id),
            )
            ->chunkById(500, function (Collection $users) use ($conversation, $createdAt): void {
                ChatConversationParticipant::query()->insertOrIgnore(
                    $users
                        ->map(fn (User $user): array => [
                            'chat_conversation_id' => $conversation->id,
                            'user_id' => $user->id,
                            'last_read_at' => $conversation->last_message_at,
                            'created_at' => $createdAt,
                            'updated_at' => $createdAt,
                        ])
                        ->all(),
                );
            });

        $conversation->unsetRelation('participants');
    }
}
