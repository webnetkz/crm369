<?php

namespace App\Observers;

use App\Models\ChatMessage;
use App\Models\User;
use App\Notifications\ChatMessagePushNotification;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Notification;

class ChatMessageObserver implements ShouldHandleEventsAfterCommit
{
    public function created(ChatMessage $chatMessage): void
    {
        $recipientUserIds = $chatMessage->conversation
            ->participants()
            ->where('user_id', '!=', $chatMessage->user_id)
            ->pluck('user_id');

        if ($recipientUserIds->isEmpty()) {
            return;
        }

        $recipients = User::query()
            ->whereKey($recipientUserIds)
            ->where('is_active', true)
            ->get();

        Notification::send($recipients, new ChatMessagePushNotification($chatMessage->loadMissing('user')));
    }
}
