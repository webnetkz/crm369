<?php

namespace App\Support;

use App\Models\ChatMessage;
use Illuminate\Support\Facades\DB;

class ChatMessageEditor
{
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

            return $message->load([
                'user:id,name,last_name,email,phone,avatar_path,avatar_scale,user_group_id',
                'attachments',
            ]);
        });
    }
}
