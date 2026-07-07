<?php

use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\ChatMessage;
use App\Models\User;
use App\Notifications\SystemNotification;

test('authenticated users can fetch unread notifications and chats for the mobile app', function () {
    $user = User::factory()->create();
    $sender = User::factory()->create();

    $user->notify(new SystemNotification(
        title: 'Read notice',
        message: 'Already opened.',
    ));

    $readNotification = $user->notifications()->latest()->firstOrFail();
    $readNotification->markAsRead();

    $user->notify(new SystemNotification(
        title: 'Security notice',
        message: 'Review your security settings.',
        actionUrl: route('security.edit'),
        actionLabel: 'Open security',
    ));

    $conversation = ChatConversation::query()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $sender->id,
        'last_message_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'last_read_at' => null,
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $sender->id,
        'last_read_at' => now(),
    ]);

    $chatMessage = ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $sender->id,
        'body' => 'Unread mobile chat message',
    ]);

    ChatConversation::query()
        ->whereKey($conversation->id)
        ->update(['last_message_at' => $chatMessage->created_at]);

    $this->actingAs($user)
        ->getJson(route('mobile.notifications.feed'))
        ->assertSuccessful()
        ->assertJsonPath('meta.user_id', $user->id)
        ->assertJsonPath('meta.notifications_unread_count', 1)
        ->assertJsonPath('meta.chat_unread_count', 1)
        ->assertJsonPath('data.notifications.0.title', 'Security notice')
        ->assertJsonPath('data.notifications.0.action_path', route('security.edit', [], false))
        ->assertJsonPath('data.chats.0.conversation_id', $conversation->id)
        ->assertJsonPath('data.chats.0.latest_message_id', $chatMessage->id)
        ->assertJsonPath('data.chats.0.message', 'Unread mobile chat message')
        ->assertJsonPath('data.chats.0.unread_count', 1)
        ->assertJsonPath('data.chats.0.action_path', route('chats.index', ['conversation' => $conversation->id], false));
});

test('guests cannot fetch the mobile notification feed', function () {
    $this->getJson(route('mobile.notifications.feed'))
        ->assertRedirect();
});
