<?php

namespace App\Notifications;

use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ChatMessagePushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private readonly int $conversationId;

    private readonly string $title;

    private readonly string $body;

    public function __construct(ChatMessage $message)
    {
        $sender = $message->user;
        $senderName = trim(collect([$sender?->name, $sender?->last_name])->filter()->implode(' '));

        $this->conversationId = $message->chat_conversation_id;
        $this->title = $senderName !== '' ? $senderName : __('New chat message');
        $this->body = trim($message->body) !== '' ? trim($message->body) : __('Attachment');

        $this->afterCommit();
        $this->onConnection((string) config('realtime.notifications.queue_connection', config('queue.default')));
        $this->onQueue((string) config('realtime.notifications.queue', 'notifications'));
    }

    /**
     * @return array<int, class-string<FirebaseChannel>>
     */
    public function via(object $notifiable): array
    {
        return [FirebaseChannel::class];
    }

    /**
     * @return array{title: string, body: string, type: string, action_path: string, entity_id: int}
     */
    public function toFirebase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'type' => 'chat_message',
            'action_path' => '/chats?conversation='.$this->conversationId,
            'entity_id' => $this->conversationId,
        ];
    }
}
