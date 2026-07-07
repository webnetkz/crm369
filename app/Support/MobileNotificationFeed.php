<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;

class MobileNotificationFeed
{
    public function __construct(
        private readonly ChatSidebarData $chatSidebarData,
    ) {}

    /**
     * @return array{
     *     data: array{
     *         notifications: array<int, array{
     *             key: string,
     *             id: string,
     *             title: string,
     *             message: string,
     *             action_path: string|null,
     *             created_at: string|null
     *         }>,
     *         chats: array<int, array{
     *             key: string,
     *             conversation_id: int,
     *             latest_message_id: int|null,
     *             title: string,
     *             message: string,
     *             unread_count: int,
     *             action_path: string,
     *             created_at: string|null
     *         }>
     *     },
     *     meta: array{user_id: int, notifications_unread_count: int, chat_unread_count: int}
     * }
     */
    public function build(User $user): array
    {
        $notifications = $this->notifications($user);
        $chatItems = $this->chatItems($user);

        return [
            'data' => [
                'notifications' => $notifications,
                'chats' => $chatItems,
            ],
            'meta' => [
                'user_id' => $user->id,
                'notifications_unread_count' => count($notifications),
                'chat_unread_count' => array_sum(array_column($chatItems, 'unread_count')),
            ],
        ];
    }

    /**
     * @return array<int, array{key: string, id: string, title: string, message: string, action_path: string|null, created_at: string|null}>
     */
    private function notifications(User $user): array
    {
        if (! Schema::hasTable('notifications')) {
            return [];
        }

        return $user->unreadNotifications()
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => [
                'key' => 'notification:'.$notification->id,
                'id' => $notification->id,
                'title' => (string) data_get($notification->data, 'title', __('ui.notifications.default_title')),
                'message' => (string) data_get($notification->data, 'message', ''),
                'action_path' => $this->normalizeActionPath(data_get($notification->data, 'action_url')),
                'created_at' => $notification->created_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key: string, conversation_id: int, latest_message_id: int|null, title: string, message: string, unread_count: int, action_path: string, created_at: string|null}>
     */
    private function chatItems(User $user): array
    {
        if (! $this->canAccessChats($user)) {
            return [];
        }

        return collect($this->chatSidebarData->unreadConversations($user))
            ->map(fn (array $conversation): array => [
                'key' => sprintf(
                    'chat:%d:%s',
                    $conversation['id'],
                    $conversation['latestMessageId'] ?? 'latest',
                ),
                'conversation_id' => $conversation['id'],
                'latest_message_id' => $conversation['latestMessageId'],
                'title' => $conversation['title'],
                'message' => $conversation['excerpt'] ?? __('ui.chat.title'),
                'unread_count' => $conversation['unreadCount'],
                'action_path' => route('chats.index', ['conversation' => $conversation['id']], false),
                'created_at' => $conversation['lastMessageAt'],
            ])
            ->values()
            ->all();
    }

    private function canAccessChats(User $user): bool
    {
        return $user->email_verified_at !== null
            && $user->canAccessChats()
            && Schema::hasTable('chat_conversations')
            && Schema::hasTable('chat_conversation_participants')
            && Schema::hasTable('chat_messages');
    }

    private function normalizeActionPath(mixed $actionUrl): ?string
    {
        if (! is_string($actionUrl)) {
            return null;
        }

        $trimmedActionUrl = trim($actionUrl);

        if ($trimmedActionUrl === '') {
            return null;
        }

        if (str_starts_with($trimmedActionUrl, '/')) {
            return $trimmedActionUrl;
        }

        $parts = parse_url($trimmedActionUrl);

        if ($parts === false) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $path.$query.$fragment;
    }
}
