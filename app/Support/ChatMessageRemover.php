<?php

namespace App\Support;

use App\Models\ChatMessage;
use Illuminate\Support\Facades\DB;

class ChatMessageRemover
{
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
            ])->save();

            return $message->load([
                'user:id,name,last_name,email,phone,avatar_path,avatar_scale,user_group_id',
                'attachments',
            ]);
        });
    }
}
