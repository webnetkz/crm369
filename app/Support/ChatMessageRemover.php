<?php

namespace App\Support;

use App\Models\ChatMessage;
use Illuminate\Support\Facades\DB;

class ChatMessageRemover
{
    public function __construct(
        private readonly ChatRuntimeCache $chatRuntimeCache,
    ) {}

    public function remove(ChatMessage $message): ChatMessage
    {
        return DB::transaction(function () use ($message): ChatMessage {
            if ($message->wasDeleted()) {
                return $message->load([
                    'user:id,name,last_name,email,phone,avatar_path,avatar_scale,user_group_id',
                    'attachments',
                ]);
            }

            $message->forceFill([
                'deleted_at' => now(),
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
