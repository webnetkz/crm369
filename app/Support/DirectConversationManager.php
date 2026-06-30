<?php

namespace App\Support;

use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DirectConversationManager
{
    public function ensure(User $initiator, User $recipient): ChatConversation
    {
        $conversation = ChatConversation::query()
            ->where('type', ChatConversation::TYPE_DIRECT)
            ->whereHas('participants', fn (Builder $query) => $query->where('user_id', $initiator->id))
            ->whereHas('participants', fn (Builder $query) => $query->where('user_id', $recipient->id))
            ->has('participants', '=', 2)
            ->first();

        if ($conversation) {
            return $conversation;
        }

        return DB::transaction(function () use ($initiator, $recipient): ChatConversation {
            $conversation = ChatConversation::query()->create([
                'type' => ChatConversation::TYPE_DIRECT,
                'created_by_user_id' => $initiator->id,
            ]);

            ChatConversationParticipant::query()->create([
                'chat_conversation_id' => $conversation->id,
                'user_id' => $initiator->id,
                'last_read_at' => now(),
            ]);

            ChatConversationParticipant::query()->create([
                'chat_conversation_id' => $conversation->id,
                'user_id' => $recipient->id,
            ]);

            return $conversation;
        });
    }
}
