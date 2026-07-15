<?php

namespace App\Support;

use App\Models\ChatMessage;
use Illuminate\Support\Facades\DB;

class ChatMessageEditor
{
    public function __construct(
        private readonly ChatRuntimeCache $chatRuntimeCache,
    ) {}

    public function edit(ChatMessage $message, string $body): ChatMessage
    {
        return DB::transaction(function () use ($body, $message): ChatMessage {
            if ($message->body === $body) {
                return $message->load([
                    'user:id,name,last_name,email,phone,avatar_path,avatar_scale,user_group_id',
                    'attachments',
                ]);
            }

            $message->forceFill([
                'original_body' => $message->original_body ?? $message->body,
                'body' => $body,
                'edited_at' => now(),
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
