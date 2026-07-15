<?php

namespace App\Support;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChatMessagePinner
{
    public function __construct(
        private readonly ChatRuntimeCache $chatRuntimeCache,
    ) {}

    public function pin(ChatMessage $message, User $user): ChatMessage
    {
        return DB::transaction(function () use ($message, $user): ChatMessage {
            if (
                $message->isPinned() &&
                $message->pinned_by_user_id === $user->id
            ) {
                return $message->load([
                    'user:id,name,last_name,email,phone,avatar_path,avatar_scale,user_group_id',
                    'attachments',
                ]);
            }

            $message->forceFill([
                'pinned_at' => now(),
                'pinned_by_user_id' => $user->id,
            ])->save();

            $message->loadMissing('conversation.participants:id,chat_conversation_id,user_id');

            if ($message->conversation !== null) {
                $this->chatRuntimeCache->forgetConversation($message->conversation);
            }

            return $message->load([
                'user:id,name,last_name,email,phone,avatar_path,avatar_scale,user_group_id',
                'attachments',
            ]);
        });
    }

    public function unpin(ChatMessage $message): ChatMessage
    {
        return DB::transaction(function () use ($message): ChatMessage {
            if (! $message->isPinned()) {
                return $message->load([
                    'user:id,name,last_name,email,phone,avatar_path,avatar_scale,user_group_id',
                    'attachments',
                ]);
            }

            $message->forceFill([
                'pinned_at' => null,
                'pinned_by_user_id' => null,
            ])->save();

            $message->loadMissing('conversation.participants:id,chat_conversation_id,user_id');

            if ($message->conversation !== null) {
                $this->chatRuntimeCache->forgetConversation($message->conversation);
            }

            return $message->load([
                'user:id,name,last_name,email,phone,avatar_path,avatar_scale,user_group_id',
                'attachments',
            ]);
        });
    }
}
