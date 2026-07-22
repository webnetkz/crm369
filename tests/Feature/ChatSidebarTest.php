<?php

use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\ChatMessage;
use App\Models\ChatMessageRead;
use App\Models\User;
use App\Models\UserGroup;
use App\Support\ChatRuntimeCache;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users receive chat unread count in shared props', function () {
    $user = User::factory()->create();
    $sender = User::factory()->create();

    $conversation = ChatConversation::query()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $sender->id,
        'last_message_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $user->id,
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $sender->id,
        'last_read_at' => now(),
    ]);

    ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $sender->id,
        'body' => 'Hello from support',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('chat.unreadCount', 1));
});

test('chat shared props are invalidated after a conversation is opened and marked as read', function () {
    $user = User::factory()->create();
    $sender = User::factory()->create();

    $conversation = ChatConversation::query()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $sender->id,
        'last_message_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $user->id,
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $sender->id,
        'last_read_at' => now(),
    ]);

    ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $sender->id,
        'body' => 'Unread message',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('chat.unreadCount', 1));

    $this->actingAs($user)
        ->get(route('chats.sidebar', ['conversation' => $conversation->id]))
        ->assertSuccessful()
        ->assertJsonPath('unreadCount', 0);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('chat.unreadCount', 0));
});

test('chat shared props are invalidated after a new message arrives', function () {
    $user = User::factory()->create();
    $sender = User::factory()->create();

    $conversation = ChatConversation::query()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $sender->id,
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'last_read_at' => now()->subMinute(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $sender->id,
        'last_read_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('chat.unreadCount', 0));

    $this->actingAs($sender)
        ->post(route('chats.messages.store', $conversation), [
            'body' => 'New unread message',
        ])
        ->assertSuccessful();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('chat.unreadCount', 1));
});

test('users can start a direct chat and load it in the sidebar payload', function () {
    $user = User::factory()->create();
    $recipient = User::factory()->create([
        'phone' => '+7 777 123 45 67',
    ]);

    $response = $this->actingAs($user)
        ->post(route('chats.direct.store'), [
            'user_id' => $recipient->id,
        ])
        ->assertSuccessful();

    $conversationId = $response->json('conversationId');

    expect($conversationId)->toBeInt();

    $this->actingAs($user)
        ->get(route('chats.sidebar', ['conversation' => $conversationId]))
        ->assertSuccessful()
        ->assertJsonPath('activeConversation.id', $conversationId)
        ->assertJsonPath('activeConversation.title', trim($recipient->name.' '.($recipient->last_name ?? '')))
        ->assertJsonPath('activeConversation.participant.phone', $recipient->phone);
});

test('general chat is created automatically and always stays first in the chats list', function () {
    $user = User::factory()->create();
    $recipient = User::factory()->create();

    $directConversation = ChatConversation::query()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $recipient->id,
        'last_message_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $directConversation->id,
        'user_id' => $user->id,
        'last_read_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $directConversation->id,
        'user_id' => $recipient->id,
        'last_read_at' => now(),
    ]);

    ChatMessage::query()->create([
        'chat_conversation_id' => $directConversation->id,
        'user_id' => $recipient->id,
        'body' => 'Direct message',
    ]);

    $response = $this->actingAs($user)
        ->get(route('chats.sidebar'))
        ->assertSuccessful();

    $generalConversationId = $response->json('conversations.0.id');

    expect($response->json('conversations.0.type'))->toBe(ChatConversation::TYPE_GENERAL)
        ->and($response->json('conversations.0.title'))->toBe(ChatConversation::GENERAL_CHAT_TITLE)
        ->and($response->json('conversations.1.id'))->toBe($directConversation->id)
        ->and(ChatConversation::query()->whereKey($generalConversationId)->value('system_key'))->toBe(ChatConversation::SYSTEM_KEY_GENERAL)
        ->and(ChatConversationParticipant::query()
            ->where('chat_conversation_id', $generalConversationId)
            ->where('user_id', $user->id)
            ->exists())->toBeTrue();
});

test('all users can exchange messages in the general chat', function () {
    $author = User::factory()->create();
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('chat.unreadCount', 0));

    $generalConversationId = $this->actingAs($author)
        ->get(route('chats.sidebar'))
        ->assertSuccessful()
        ->json('conversations.0.id');

    $this->actingAs($author)
        ->post(route('chats.messages.store', $generalConversationId), [
            'body' => 'Сообщение для всех',
        ])
        ->assertSuccessful()
        ->assertJsonPath('message.body', 'Сообщение для всех');

    $this->actingAs($viewer)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('chat.unreadCount', 1));

    $this->actingAs($viewer)
        ->get(route('chats.sidebar', ['conversation' => $generalConversationId]))
        ->assertSuccessful()
        ->assertJsonPath('activeConversation.id', $generalConversationId)
        ->assertJsonPath('activeConversation.type', ChatConversation::TYPE_GENERAL)
        ->assertJsonPath('activeConversation.title', ChatConversation::GENERAL_CHAT_TITLE)
        ->assertJsonPath('activeConversation.messages.0.body', 'Сообщение для всех')
        ->assertJsonPath('unreadCount', 0);
});

test('opening a general chat only clears the notification for that user and records exact viewers', function () {
    $author = User::factory()->create([
        'name' => 'Author',
        'last_name' => 'User',
    ]);
    $firstViewer = User::factory()->create([
        'name' => 'First',
        'last_name' => 'Viewer',
    ]);
    $secondViewer = User::factory()->create([
        'name' => 'Second',
        'last_name' => 'Viewer',
    ]);

    $generalConversationId = $this->actingAs($author)
        ->get(route('chats.sidebar'))
        ->assertSuccessful()
        ->json('conversations.0.id');

    $this->actingAs($author)
        ->post(route('chats.messages.store', $generalConversationId), [
            'body' => 'Общее сообщение с отдельными уведомлениями',
        ])
        ->assertSuccessful();

    $message = ChatMessage::query()->latest('id')->firstOrFail();

    $this->actingAs($firstViewer)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('chat.unreadCount', 1));

    $this->actingAs($secondViewer)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('chat.unreadCount', 1));

    $this->actingAs($firstViewer)
        ->get(route('chats.sidebar', ['conversation' => $generalConversationId]))
        ->assertSuccessful()
        ->assertJsonPath('unreadCount', 0);

    $this->actingAs($firstViewer)
        ->get(route('chats.sidebar', ['conversation' => $generalConversationId]))
        ->assertSuccessful();

    $this->actingAs($firstViewer)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('chat.unreadCount', 0));

    $this->actingAs($secondViewer)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('chat.unreadCount', 1));

    $this->actingAs($author)
        ->get(route('chats.sidebar', ['conversation' => $generalConversationId]))
        ->assertSuccessful()
        ->assertJsonPath('activeConversation.messages.0.readCount', 1)
        ->assertJsonPath('activeConversation.messages.0.readBy.0.id', $firstViewer->id)
        ->assertJsonPath('activeConversation.messages.0.readBy.0.name', 'First Viewer');

    expect(ChatMessageRead::query()
        ->where('chat_message_id', $message->id)
        ->where('user_id', $firstViewer->id)
        ->count())->toBe(1)
        ->and(ChatMessageRead::query()
            ->where('chat_message_id', $message->id)
            ->where('user_id', $secondViewer->id)
            ->exists())->toBeFalse();

    $this->actingAs($secondViewer)
        ->get(route('chats.sidebar', ['conversation' => $generalConversationId]))
        ->assertSuccessful();

    $this->actingAs($author)
        ->get(route('chats.sidebar', ['conversation' => $generalConversationId]))
        ->assertSuccessful()
        ->assertJsonPath('activeConversation.messages.0.readCount', 2)
        ->assertJsonPath('activeConversation.messages.0.readBy.1.id', $secondViewer->id)
        ->assertJsonPath('activeConversation.messages.0.readBy.1.name', 'Second Viewer');
});

test('general chat unread notifications remain for every user who has not opened the conversation', function () {
    $author = User::factory()->create();
    $recipients = User::factory()->count(9)->create();

    $generalConversationId = $this->actingAs($author)
        ->get(route('chats.sidebar'))
        ->assertSuccessful()
        ->json('conversations.0.id');

    foreach ($recipients as $recipient) {
        $this->actingAs($recipient)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page->where('chat.unreadCount', 0));
    }

    $this->actingAs($author)
        ->post(route('chats.messages.store', $generalConversationId), [
            'body' => 'Сообщение для всех пользователей',
        ])
        ->assertSuccessful();

    $message = ChatMessage::query()->latest('id')->firstOrFail();

    foreach ($recipients as $recipient) {
        $this->actingAs($recipient)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page->where('chat.unreadCount', 1));
    }

    $readers = $recipients->take(5);
    $unreadRecipients = $recipients->skip(5);

    foreach ($readers as $reader) {
        $this->actingAs($reader)
            ->get(route('chats.sidebar', ['conversation' => $generalConversationId]))
            ->assertSuccessful()
            ->assertJsonPath('unreadCount', 0);
    }

    foreach ($readers as $reader) {
        $this->actingAs($reader)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page->where('chat.unreadCount', 0));
    }

    foreach ($unreadRecipients as $unreadRecipient) {
        $this->actingAs($unreadRecipient)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page->where('chat.unreadCount', 1));

        $this->actingAs($unreadRecipient)
            ->getJson(route('mobile.notifications.feed'))
            ->assertSuccessful()
            ->assertJsonPath('meta.chat_unread_count', 1)
            ->assertJsonPath('data.chats.0.conversation_id', $generalConversationId)
            ->assertJsonPath('data.chats.0.latest_message_id', $message->id);
    }

    expect(ChatMessageRead::query()
        ->where('chat_message_id', $message->id)
        ->pluck('user_id')
        ->sort()
        ->values()
        ->all())->toBe($readers->pluck('id')->sort()->values()->all());
});

test('general chat messages stay unread for active users without verified emails until they open the chat', function () {
    $author = User::factory()->create();
    $firstReader = User::factory()->create();
    $waitingRecipient = User::factory()->create([
        'email_verified_at' => null,
        'is_active' => true,
    ]);

    $generalConversationId = $this->actingAs($author)
        ->get(route('chats.sidebar'))
        ->assertSuccessful()
        ->json('conversations.0.id');

    $this->actingAs($author)
        ->post(route('chats.messages.store', $generalConversationId), [
            'body' => 'Новое сообщение для каждого активного пользователя',
        ])
        ->assertSuccessful();

    $message = ChatMessage::query()->latest('id')->firstOrFail();

    expect(ChatConversationParticipant::query()
        ->where('chat_conversation_id', $generalConversationId)
        ->where('user_id', $waitingRecipient->id)
        ->exists())->toBeTrue();

    $this->actingAs($firstReader)
        ->get(route('chats.sidebar', ['conversation' => $generalConversationId]))
        ->assertSuccessful()
        ->assertJsonPath('unreadCount', 0);

    $this->actingAs($waitingRecipient)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('chat.unreadCount', 1));

    $this->actingAs($waitingRecipient)
        ->get(route('chats.sidebar'))
        ->assertSuccessful()
        ->assertJsonPath('unreadCount', 1)
        ->assertJsonPath('conversations.0.id', $generalConversationId)
        ->assertJsonPath('conversations.0.unreadCount', 1);

    expect(ChatMessageRead::query()
        ->where('chat_message_id', $message->id)
        ->where('user_id', $firstReader->id)
        ->exists())->toBeTrue()
        ->and(ChatMessageRead::query()
            ->where('chat_message_id', $message->id)
            ->where('user_id', $waitingRecipient->id)
            ->exists())->toBeFalse();
});

test('database unread state ignores stale personalized chat cache', function () {
    $author = User::factory()->create();
    $reader = User::factory()->create();
    $waitingRecipient = User::factory()->create();

    $generalConversationId = $this->actingAs($author)
        ->get(route('chats.sidebar'))
        ->assertSuccessful()
        ->json('conversations.0.id');

    $this->actingAs($author)
        ->post(route('chats.messages.store', $generalConversationId), [
            'body' => 'Персональное состояние непрочитанного сообщения',
        ])
        ->assertSuccessful();

    $message = ChatMessage::query()->latest('id')->firstOrFail();

    $chatRuntimeCache = app(ChatRuntimeCache::class);

    $chatRuntimeCache->shared($waitingRecipient, fn (): array => [
        'unreadCount' => 0,
    ]);
    $chatRuntimeCache->sidebar($waitingRecipient, fn (): array => [
        'unreadCount' => 0,
        'conversations' => [],
        'contacts' => [],
        'activeConversation' => null,
    ]);
    $chatRuntimeCache->unreadConversations($waitingRecipient, 10, fn (): array => []);

    $this->actingAs($reader)
        ->get(route('chats.sidebar', ['conversation' => $generalConversationId]))
        ->assertSuccessful()
        ->assertJsonPath('unreadCount', 0);

    $this->actingAs($waitingRecipient)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->where('chat.unreadCount', 1));

    $this->actingAs($waitingRecipient)
        ->get(route('chats.sidebar'))
        ->assertSuccessful()
        ->assertJsonPath('unreadCount', 1)
        ->assertJsonPath('conversations.0.id', $generalConversationId)
        ->assertJsonPath('conversations.0.unreadCount', 1);

    $this->actingAs($waitingRecipient)
        ->getJson(route('mobile.notifications.feed'))
        ->assertSuccessful()
        ->assertJsonPath('meta.chat_unread_count', 1)
        ->assertJsonPath('data.chats.0.conversation_id', $generalConversationId);

    expect(ChatConversationParticipant::query()
        ->where('chat_conversation_id', $generalConversationId)
        ->where('user_id', $waitingRecipient->id)
        ->value('last_read_at'))->toBeNull()
        ->and(ChatMessageRead::query()
            ->where('chat_message_id', $message->id)
            ->where('user_id', $waitingRecipient->id)
            ->exists())->toBeFalse();
});

test('direct chat messages expose a read check after the recipient opens the conversation', function () {
    $author = User::factory()->create();
    $recipient = User::factory()->create([
        'name' => 'Direct',
        'last_name' => 'Recipient',
    ]);

    $conversation = ChatConversation::query()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $author->id,
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $author->id,
        'last_read_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
    ]);

    $this->actingAs($author)
        ->post(route('chats.messages.store', $conversation), [
            'body' => 'Сообщение с галочкой прочтения',
        ])
        ->assertSuccessful()
        ->assertJsonPath('message.isRead', false)
        ->assertJsonPath('message.readCount', 0)
        ->assertJsonPath('message.readBy', []);

    $message = ChatMessage::query()->latest('id')->firstOrFail();

    $this->actingAs($recipient)
        ->get(route('chats.sidebar', ['conversation' => $conversation->id]))
        ->assertSuccessful();

    $this->actingAs($author)
        ->get(route('chats.sidebar', ['conversation' => $conversation->id]))
        ->assertSuccessful()
        ->assertJsonPath('activeConversation.messages.0.isRead', true)
        ->assertJsonPath('activeConversation.messages.0.readCount', 1)
        ->assertJsonPath('activeConversation.messages.0.readBy.0.id', $recipient->id)
        ->assertJsonPath('activeConversation.messages.0.readBy.0.name', 'Direct Recipient');

    expect(ChatMessageRead::query()
        ->where('chat_message_id', $message->id)
        ->where('user_id', $recipient->id)
        ->count())->toBe(1);
});

test('general chat can be created before the system key migration is applied', function () {
    ChatConversation::flushSystemKeySupportCache();

    Schema::table('chat_conversations', function (Blueprint $table) {
        $table->dropUnique(['system_key']);
        $table->dropColumn('system_key');
    });

    ChatConversation::flushSystemKeySupportCache();

    try {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('chats.sidebar'))
            ->assertSuccessful()
            ->assertJsonPath('conversations.0.type', ChatConversation::TYPE_GENERAL)
            ->assertJsonPath('conversations.0.title', ChatConversation::GENERAL_CHAT_TITLE);

        expect(ChatConversation::query()
            ->where('type', ChatConversation::TYPE_GENERAL)
            ->count())->toBe(1);
    } finally {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->string('system_key')->nullable()->unique()->after('type');
        });

        ChatConversation::flushSystemKeySupportCache();
    }
});

test('chat profile endpoint returns the same managed profile fields used by the users sidebar', function () {
    $viewer = User::factory()->create();
    $recipient = User::factory()->create([
        'name' => 'Jane',
        'last_name' => 'Doe',
        'phone' => '+77771234567',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($viewer)
        ->get(route('chats.users.show', $recipient))
        ->assertSuccessful()
        ->assertJsonPath('data.id', $recipient->id)
        ->assertJsonPath('data.name', 'Jane')
        ->assertJsonPath('data.last_name', 'Doe')
        ->assertJsonPath('data.phone', '+77771234567')
        ->assertJsonPath('data.email_verified_at', $recipient->email_verified_at?->toISOString())
        ->assertJsonPath('managerOptions', [])
        ->assertJsonPath('canEdit', false);
});

test('chat profile endpoint returns manager options for viewers who can manage accounts', function () {
    $administrators = UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);

    $viewer = User::factory()->create([
        'user_group_id' => $administrators->id,
    ]);
    $recipient = User::factory()->create();
    $manager = User::factory()->create([
        'name' => 'Aruzhan',
        'last_name' => 'Sarsenova',
        'middle_name' => 'Bauyrzhanovna',
    ]);

    $this->actingAs($viewer)
        ->get(route('chats.users.show', $recipient))
        ->assertSuccessful()
        ->assertJsonPath('canEdit', true)
        ->assertJsonPath('managerOptions.0.avatar_scale', fn (mixed $value): bool => is_numeric($value))
        ->assertJsonPath('managerOptions', function (array $options) use ($manager): bool {
            return collect($options)->contains(function (array $option) use ($manager): bool {
                return $option['id'] === $manager->id
                    && $option['full_name'] === 'Aruzhan Sarsenova Bauyrzhanovna';
            });
        });
});

test('opening a chat marks incoming messages as read and users can send replies', function () {
    $user = User::factory()->create();
    $recipient = User::factory()->create();

    $conversation = ChatConversation::query()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $recipient->id,
        'last_message_at' => now(),
    ]);

    $participant = ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'last_read_at' => null,
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
        'last_read_at' => now(),
    ]);

    ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
        'body' => 'Unread message',
    ]);

    $this->actingAs($user)
        ->get(route('chats.sidebar', ['conversation' => $conversation->id]))
        ->assertSuccessful()
        ->assertJsonPath('unreadCount', 0);

    expect($participant->refresh()->last_read_at)->not->toBeNull();

    $this->actingAs($user)
        ->post(route('chats.messages.store', $conversation), [
            'body' => 'Reply message',
        ])
        ->assertSuccessful()
        ->assertJsonPath('message.body', 'Reply message');

    $message = ChatMessage::query()
        ->where('chat_conversation_id', $conversation->id)
        ->where('user_id', $user->id)
        ->latest('id')
        ->first();

    expect($message)->not->toBeNull()
        ->and($conversation->refresh()->last_message_at)->not->toBeNull();
});

test('chat messages preserve line breaks when stored and returned', function () {
    $user = User::factory()->create();
    $recipient = User::factory()->create();

    $conversation = ChatConversation::query()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $recipient->id,
        'last_message_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'last_read_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
        'last_read_at' => now(),
    ]);

    $body = "First line\nSecond line";

    $this->actingAs($user)
        ->post(route('chats.messages.store', $conversation), [
            'body' => $body,
        ])
        ->assertSuccessful()
        ->assertJsonPath('message.body', $body);

    $message = ChatMessage::query()
        ->where('chat_conversation_id', $conversation->id)
        ->where('user_id', $user->id)
        ->latest('id')
        ->first();

    expect($message?->body)->toBe($body);

    $this->actingAs($user)
        ->get(route('chats.sidebar', ['conversation' => $conversation->id]))
        ->assertSuccessful()
        ->assertJsonPath('activeConversation.messages.0.body', $body);
});

test('chat sidebar returns full message history across multiple days', function () {
    $user = User::factory()->create();
    $recipient = User::factory()->create();

    $conversation = ChatConversation::query()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $recipient->id,
        'last_message_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'last_read_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
        'last_read_at' => now(),
    ]);

    $firstSentAt = now()->subDays(4)->startOfDay()->addHours(9);

    foreach (range(1, 55) as $index) {
        $sentAt = $firstSentAt->copy()->addHours($index - 1);

        ChatMessage::query()->create([
            'chat_conversation_id' => $conversation->id,
            'user_id' => $index % 2 === 0 ? $user->id : $recipient->id,
            'body' => "History message {$index}",
            'created_at' => $sentAt,
            'updated_at' => $sentAt,
        ]);
    }

    $conversation->update([
        'last_message_at' => $firstSentAt->copy()->addHours(54),
    ]);

    $this->actingAs($user)
        ->get(route('chats.sidebar', ['conversation' => $conversation->id]))
        ->assertSuccessful()
        ->assertJsonCount(55, 'activeConversation.messages')
        ->assertJsonPath('activeConversation.messages.0.body', 'History message 1')
        ->assertJsonPath('activeConversation.messages.54.body', 'History message 55')
        ->assertJsonPath('activeConversation.messages.0.createdAt', fn (mixed $value): bool => is_string($value) && $value !== '')
        ->assertJsonPath('activeConversation.messages.54.createdAt', fn (mixed $value): bool => is_string($value) && $value !== '');
});

test('users can edit their messages while preserving the original body', function () {
    $user = User::factory()->create();
    $recipient = User::factory()->create();

    $conversation = ChatConversation::query()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $recipient->id,
        'last_message_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'last_read_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
        'last_read_at' => now(),
    ]);

    $message = ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'body' => 'Первый текст',
    ]);

    $this->actingAs($user)
        ->patch(route('chats.messages.update', [$conversation, $message]), [
            'body' => 'Второй текст',
        ])
        ->assertSuccessful()
        ->assertJsonPath('message.body', 'Второй текст')
        ->assertJsonPath('message.isEdited', true)
        ->assertJsonPath('message.editedAt', fn (mixed $value): bool => is_string($value) && $value !== '');

    $this->actingAs($user)
        ->patch(route('chats.messages.update', [$conversation, $message]), [
            'body' => 'Третий текст',
        ])
        ->assertSuccessful()
        ->assertJsonPath('message.body', 'Третий текст')
        ->assertJsonPath('message.isEdited', true);

    expect($message->refresh())
        ->body->toBe('Третий текст')
        ->original_body->toBe('Первый текст')
        ->edited_at->not->toBeNull();

    $this->actingAs($user)
        ->get(route('chats.sidebar', ['conversation' => $conversation->id]))
        ->assertSuccessful()
        ->assertJsonPath('activeConversation.messages.0.body', 'Третий текст')
        ->assertJsonPath('activeConversation.messages.0.isEdited', true)
        ->assertJsonPath('activeConversation.messages.0.editedAt', fn (mixed $value): bool => is_string($value) && $value !== '');
});

test('users can pin and unpin chat messages', function () {
    $user = User::factory()->create();
    $recipient = User::factory()->create();

    $conversation = ChatConversation::query()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $recipient->id,
        'last_message_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'last_read_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
        'last_read_at' => now(),
    ]);

    $message = ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
        'body' => 'Important update',
    ]);

    $this->actingAs($user)
        ->patch(route('chats.messages.pin', [$conversation, $message]))
        ->assertSuccessful()
        ->assertJsonPath('message.isPinned', true)
        ->assertJsonPath('message.pinnedAt', fn (mixed $value): bool => is_string($value) && $value !== '');

    expect($message->refresh()->isPinned())->toBeTrue()
        ->and($message->pinned_by_user_id)->toBe($user->id);

    $this->actingAs($user)
        ->get(route('chats.sidebar', ['conversation' => $conversation->id]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'activeConversation.pinnedMessages')
        ->assertJsonPath('activeConversation.pinnedMessages.0.id', $message->id)
        ->assertJsonPath('activeConversation.pinnedMessages.0.isPinned', true);

    $this->actingAs($user)
        ->delete(route('chats.messages.unpin', [$conversation, $message]))
        ->assertSuccessful()
        ->assertJsonPath('message.isPinned', false)
        ->assertJsonPath('message.pinnedAt', null);

    expect($message->refresh()->isPinned())->toBeFalse()
        ->and($message->pinned_by_user_id)->toBeNull();

    $this->actingAs($user)
        ->get(route('chats.sidebar', ['conversation' => $conversation->id]))
        ->assertSuccessful()
        ->assertJsonCount(0, 'activeConversation.pinnedMessages');
});

test('users cannot edit messages created by other participants', function () {
    $user = User::factory()->create();
    $recipient = User::factory()->create();

    $conversation = ChatConversation::query()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $recipient->id,
        'last_message_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'last_read_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
        'last_read_at' => now(),
    ]);

    $message = ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
        'body' => 'Original recipient text',
    ]);

    $this->actingAs($user)
        ->patch(route('chats.messages.update', [$conversation, $message]), [
            'body' => 'Forbidden edit',
        ])
        ->assertForbidden();

    expect($message->refresh())
        ->body->toBe('Original recipient text')
        ->original_body->toBeNull()
        ->edited_at->toBeNull();
});

test('users can delete their messages while preserving the original record', function () {
    $user = User::factory()->create();
    $recipient = User::factory()->create();

    $conversation = ChatConversation::query()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $recipient->id,
        'last_message_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'last_read_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
        'last_read_at' => now(),
    ]);

    $message = ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'body' => 'Удаляемый текст',
    ]);

    $this->actingAs($user)
        ->delete(route('chats.messages.destroy', [$conversation, $message]))
        ->assertSuccessful()
        ->assertJsonPath('message.body', __('ui.chat.deleted_message'))
        ->assertJsonPath('message.isDeleted', true)
        ->assertJsonPath('message.deletedAt', fn (mixed $value): bool => is_string($value) && $value !== '')
        ->assertJsonPath('message.attachments', []);

    expect($message->refresh())
        ->body->toBe('Удаляемый текст')
        ->deleted_at->not->toBeNull();

    $this->actingAs($user)
        ->get(route('chats.sidebar', ['conversation' => $conversation->id]))
        ->assertSuccessful()
        ->assertJsonPath('activeConversation.messages.0.body', __('ui.chat.deleted_message'))
        ->assertJsonPath('activeConversation.messages.0.isDeleted', true)
        ->assertJsonPath('activeConversation.messages.0.attachments', [])
        ->assertJsonPath('conversations.1.excerpt', __('ui.chat.deleted_message'));
});

test('users cannot delete messages created by other participants', function () {
    $user = User::factory()->create();
    $recipient = User::factory()->create();

    $conversation = ChatConversation::query()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $recipient->id,
        'last_message_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'last_read_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
        'last_read_at' => now(),
    ]);

    $message = ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
        'body' => 'Чужое сообщение',
    ]);

    $this->actingAs($user)
        ->delete(route('chats.messages.destroy', [$conversation, $message]))
        ->assertForbidden();

    expect($message->refresh()->deleted_at)->toBeNull();
});

test('chat messages can include attachments and expose download links', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $recipient = User::factory()->create();

    $conversation = ChatConversation::query()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $recipient->id,
        'last_message_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'last_read_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
        'last_read_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('chats.messages.store', $conversation), [
            'body' => 'Attached file',
            'attachments' => [
                UploadedFile::fake()->create('brief.txt', 12, 'text/plain'),
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('message.attachments.0.name', 'brief.txt');

    $message = ChatMessage::query()->with('attachments')->latest('id')->firstOrFail();
    $attachment = $message->attachments->first();

    expect($attachment)->not->toBeNull();
    Storage::disk('local')->assertExists((string) $attachment?->path);

    $this->actingAs($user)
        ->get(route('chats.sidebar', ['conversation' => $conversation->id]))
        ->assertSuccessful()
        ->assertJsonPath('activeConversation.messages.0.attachments.0.name', 'brief.txt')
        ->assertJsonPath('activeConversation.messages.0.attachments.0.previewUrl', null)
        ->assertJsonPath(
            'activeConversation.messages.0.attachments.0.downloadUrl',
            route('chats.attachments.download', $attachment),
        );
});

test('image chat attachments expose preview links and can be rendered inline', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $recipient = User::factory()->create();

    $conversation = ChatConversation::query()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $recipient->id,
        'last_message_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'last_read_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
        'last_read_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('chats.messages.store', $conversation), [
            'body' => 'Attached image',
            'attachments' => [
                UploadedFile::fake()->create('preview.png', 12, 'image/png'),
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('message.attachments.0.name', 'preview.png');

    $attachment = ChatMessage::query()->with('attachments')->latest('id')->firstOrFail()
        ->attachments
        ->firstOrFail();

    $previewUrl = route('chats.attachments.preview', $attachment);

    $this->actingAs($user)
        ->get(route('chats.sidebar', ['conversation' => $conversation->id]))
        ->assertSuccessful()
        ->assertJsonPath('activeConversation.messages.0.attachments.0.previewUrl', $previewUrl);

    $previewResponse = $this->actingAs($user)->get($previewUrl);

    $previewResponse->assertSuccessful()
        ->assertHeader('x-content-type-options', 'nosniff');

    expect($previewResponse->headers->get('content-type'))->toContain('image/png');
});

test('audio chat attachments expose inline audio links and can be streamed in chat', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $recipient = User::factory()->create();

    $conversation = ChatConversation::query()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $recipient->id,
        'last_message_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'last_read_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
        'last_read_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('chats.messages.store', $conversation), [
            'body' => 'Attached audio',
            'attachments' => [
                UploadedFile::fake()->create('voice-note.mp3', 256, 'audio/mpeg'),
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('message.attachments.0.name', 'voice-note.mp3');

    $attachment = ChatMessage::query()->with('attachments')->latest('id')->firstOrFail()
        ->attachments
        ->firstOrFail();

    $audioUrl = route('chats.attachments.preview', $attachment);

    $this->actingAs($user)
        ->get(route('chats.sidebar', ['conversation' => $conversation->id]))
        ->assertSuccessful()
        ->assertJsonPath('activeConversation.messages.0.attachments.0.previewUrl', null)
        ->assertJsonPath('activeConversation.messages.0.attachments.0.audioUrl', $audioUrl);

    $previewResponse = $this->actingAs($user)->get($audioUrl);

    $previewResponse->assertSuccessful()
        ->assertHeader('x-content-type-options', 'nosniff');

    expect($previewResponse->headers->get('content-disposition'))->toStartWith('inline;')
        ->and($previewResponse->headers->get('content-type'))->toContain('audio/mpeg');
});

test('attachments of deleted chat messages are no longer accessible', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $recipient = User::factory()->create();

    $conversation = ChatConversation::query()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $recipient->id,
        'last_message_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'last_read_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
        'last_read_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('chats.messages.store', $conversation), [
            'body' => 'Сообщение с файлом',
            'attachments' => [
                UploadedFile::fake()->create('brief.txt', 12, 'text/plain'),
            ],
        ])
        ->assertSuccessful();

    $message = ChatMessage::query()->with('attachments')->latest('id')->firstOrFail();
    $attachment = $message->attachments->firstOrFail();

    $this->actingAs($user)
        ->delete(route('chats.messages.destroy', [$conversation, $message]))
        ->assertSuccessful();

    $this->actingAs($user)
        ->get(route('chats.attachments.download', $attachment))
        ->assertNotFound();
});

test('users cannot open or post messages into conversations they do not participate in', function () {
    $owner = User::factory()->create();
    $otherParticipant = User::factory()->create();
    $outsider = User::factory()->create();

    $conversation = ChatConversation::query()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $owner->id,
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $owner->id,
        'last_read_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $otherParticipant->id,
        'last_read_at' => now(),
    ]);

    $this->actingAs($outsider)
        ->get(route('chats.sidebar', ['conversation' => $conversation->id]))
        ->assertNotFound();

    $this->actingAs($outsider)
        ->post(route('chats.messages.store', $conversation), [
            'body' => 'Forbidden',
        ])
        ->assertForbidden();
});

test('authenticated users can open the dedicated chats page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('chats.index', [
            'mode' => 'search',
            'conversation' => 12,
            'contact' => 34,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('chats/Index')
            ->where('mode', 'search')
            ->where('initialConversationId', 12)
            ->where('initialContactId', 34));
});

test('chat ui renders header trigger, sidebar dock, and sheet targeting hooks', function () {
    $header = file_get_contents(resource_path('js/components/AppHeader.vue'));
    $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));
    $dock = file_get_contents(resource_path('js/components/ChatDock.vue'));
    $layout = file_get_contents(resource_path('js/layouts/app/AppSidebarLayout.vue'));
    $page = file_get_contents(resource_path('js/pages/chats/Index.vue'));
    $panel = file_get_contents(resource_path('js/components/ChatCenterPanel.vue'));
    $sheet = file_get_contents(resource_path('js/components/ChatCenterSheet.vue'));
    $composer = file_get_contents(resource_path('js/components/ChatMessageComposer.vue'));
    $attachments = file_get_contents(resource_path('js/components/ChatMessageAttachments.vue'));
    $emojiPicker = file_get_contents(resource_path('js/components/ChatEmojiPicker.vue'));
    $presence = file_get_contents(resource_path('js/composables/useChatCenterPresence.ts'));
    $timeline = file_get_contents(resource_path('js/composables/useChatMessageTimeline.ts'));

    expect($header)->toContain('ChatCenterSheet')
        ->and($header)->toContain('MessageSquareMore')
        ->and($sidebar)->toContain('chatsIndex()')
        ->and($sidebar)->toContain("isMenuItemVisible('chats')")
        ->and($dock)->toContain('ChatCenterSheet')
        ->and($dock)->toContain('useChatCenterPresence')
        ->and($dock)->toContain("page.props.portal.enabledModules.includes('chats')")
        ->and($dock)->toContain("page.component !== 'chats/Index'")
        ->and($dock)->toContain('v-if="shouldShowDock"')
        ->and($dock)->toContain("openChatCenter('chats'")
        ->and($dock)->toContain('entry.conversationId')
        ->and($dock)->toContain('entry.contactId')
        ->and($dock)->toContain('visibleDockEntries')
        ->and($dock)->toContain('Number(right.unreadCount > 0)')
        ->and($dock)->toContain('{{ entry.title }}')
        ->and($dock)->toContain('t.chat.unread')
        ->and($dock)->toContain(':aria-label="entryAriaLabel(entry)"')
        ->and($dock)->toContain('aria-hidden="true"')
        ->and($dock)->toContain("conversation.type === 'general'")
        ->and($dock)->toContain('page.props.portal.logoUrl')
        ->and($dock)->toContain("'bg-white object-contain p-1'")
        ->and($dock)->toContain('shouldShowAvatar(entry)')
        ->and($dock)->toContain('@error="markAvatarFailed(entry)"')
        ->and($dock)->toContain(':aria-hidden="shouldShowAvatar(entry)"')
        ->and($dock)->toContain('absolute inset-0 z-10 aspect-square size-full')
        ->and($dock)->toContain('min-w-6 shrink-0 items-center justify-center rounded-full bg-primary')
        ->and($dock)->toContain('group-hover/dock:pointer-events-auto')
        ->and($dock)->toContain('group-focus-within/dock:opacity-100')
        ->and($dock)->toContain('absolute right-0 bottom-full h-5 w-20')
        ->and($dock)->toContain('absolute right-0 bottom-full mb-3')
        ->and($dock)->toContain('fixed right-3 bottom-5')
        ->and($layout)->toContain('<ChatDock />')
        ->and($page)->toContain('ChatCenterPanel')
        ->and($page)->toContain('t.chat.title')
        ->and($sheet)->toContain('SheetContent side="right"')
        ->and($sheet)->toContain('ChatCenterPanel')
        ->and($presence)->toContain('activeChatCenterScopes')
        ->and($presence)->toContain('isAnyChatCenterVisible')
        ->and($panel)->toContain('initialConversationId')
        ->and($panel)->toContain('initialContactId')
        ->and($panel)->toContain('useChatCenterPresence')
        ->and($panel)->toContain('setChatCenterVisible(isActive)')
        ->and($panel)->toContain('showUserProfile.url')
        ->and($panel)->toContain('UserProfileSheet')
        ->and($panel)->toContain('useChatMessageTimeline')
        ->and($panel)->toContain('activeConversationMessages')
        ->and($panel)->toContain('activeConversationPinnedMessages')
        ->and($panel)->toContain('v-for="entry in timelineEntries"')
        ->and($panel)->toContain("entry.type === 'separator'")
        ->and($panel)->toContain('pin as pinMessage')
        ->and($panel)->toContain('unpin as unpinMessage')
        ->and($panel)->toContain('message.isPinned ? unpinMessage : pinMessage')
        ->and($panel)->toContain('togglePinnedMessage')
        ->and($panel)->toContain(':aria-pressed="entry.message.isPinned"')
        ->and($panel)->toContain("'bg-primary text-primary-foreground hover:bg-primary/90'")
        ->and($panel)->toContain('pinnedMessagePreview')
        ->and($panel)->toContain('scrollToMessage(message.id)')
        ->and($panel)->toContain('t.chat.pinned_messages')
        ->and($panel)->toContain('class="h-5 w-9 shrink-0 rounded-md border border-border bg-background shadow-sm transition hover:border-primary/40 hover:bg-primary/8"')
        ->and($panel)->toContain(':title="pinnedMessagePreview(message)"')
        ->and($panel)->toContain(':aria-label="pinnedMessagePreview(message)"')
        ->and($panel)->toContain(':data-message-id="entry.message.id"')
        ->and($panel)->toContain(':manager-options="managerOptions"')
        ->and($panel)->toContain('@open-user="openProfileById"')
        ->and($panel)->toContain('ChatMessageComposer')
        ->and($panel)->toContain('ChatMessageAttachments')
        ->and($panel)->toContain("formData.append('attachments[]', attachment)")
        ->and($panel)->toContain('v-model:attachments="selectedAttachments"')
        ->and($panel)->toContain('selectedProfileUser !== null')
        ->and($panel)->toContain('@click="openProfile(contact)"')
        ->and($panel)->toContain('@click="openProfile(activeConversation.participant)"')
        ->and($panel)->not->toContain('@click="openProfile(message.user)"')
        ->and($panel)->toContain('selectedProfileCanEdit')
        ->and($panel)->toContain('managedProfileForm.patch(updateUserProfile.url(user.id)')
        ->and($panel)->toContain('sidebarRequestSequence')
        ->and($panel)->toContain('sidebarAbortController')
        ->and($panel)->toContain("error instanceof DOMException && error.name === 'AbortError'")
        ->and($panel)->toContain('showScrollToLatest')
        ->and($panel)->toContain('forceScrollToBottom')
        ->and($panel)->toContain('isMessagesScrolledNearBottom')
        ->and($panel)->toContain('@scroll.passive="handleMessagesScroll"')
        ->and($panel)->toContain('t.chat.scroll_to_latest')
        ->and($panel)->toContain('<ArrowDown class="size-4" />')
        ->and($panel)->toContain('<Check class="size-3.5" />')
        ->and($panel)->toContain('<Eye class="size-3.5" />')
        ->and($panel)->toContain('entry.message.readCount')
        ->and($panel)->toContain('entry.message.readBy')
        ->and($panel)->toContain('group-hover/readers:visible')
        ->and($panel)->toContain('t.chat.seen_by')
        ->and($panel)->toContain('class="min-w-0 flex-1 text-left"')
        ->and($panel)->toContain('@click="openConversation(conversation.id)"')
        ->and($panel)->toContain('$event.stopPropagation();')
        ->and($panel)->toContain('class="max-w-[80%] min-w-0 space-y-2"')
        ->and($panel)->toContain('class="min-w-0 rounded-3xl px-4 py-3 text-sm wrap-anywhere whitespace-pre-wrap shadow-sm"')
        ->and($panel)->toContain('class="min-w-0 wrap-anywhere whitespace-pre-wrap"')
        ->and($panel)->toContain('whitespace-pre-wrap')
        ->and($panel)->toContain('loadRequestedConversation')
        ->and($composer)->toContain('ChatEmojiPicker')
        ->and($composer)->toContain('attachments.length > 0')
        ->and($composer)->toContain('type="file"')
        ->and($composer)->toContain('multiple')
        ->and($composer)->toContain('t.chat.drop_files_here')
        ->and($attachments)->toContain('attachment.downloadUrl')
        ->and($attachments)->toContain('attachment.previewUrl')
        ->and($attachments)->toContain('attachment.audioUrl')
        ->and($attachments)->toContain('<img')
        ->and($attachments)->toContain('<audio controls preload="metadata"')
        ->and($attachments)->toContain('<Volume2 class="size-4" />')
        ->and($attachments)->toContain('formatFileSize')
        ->and($emojiPicker)->toContain('DropdownMenuTrigger')
        ->and($emojiPicker)->toContain("emit('select', emoji)")
        ->and($emojiPicker)->toContain('t.chat.emoji_picker_title')
        ->and($timeline)->toContain('calendarDayKey')
        ->and($timeline)->toContain("type: 'separator'")
        ->and($timeline)->toContain('timelineEntries')
        ->and($timeline)->toContain("hour: '2-digit'")
        ->and($timeline)->toContain("minute: '2-digit'")
        ->and($timeline)->toContain("dateStyle: 'medium'")
        ->and($emojiPicker)->toContain('emojiOptions');
});

test('chat scroll-to-latest translations are available', function () {
    expect(__('ui.chat.scroll_to_latest', [], 'en'))
        ->toBe('Scroll to latest message')
        ->and(__('ui.chat.scroll_to_latest', [], 'ru'))
        ->toBe('Прокрутить к последнему сообщению')
        ->and(__('ui.chat.pin_message', [], 'en'))
        ->toBe('Pin')
        ->and(__('ui.chat.unpin_message', [], 'ru'))
        ->toBe('Открепить')
        ->and(__('ui.chat.pinned_messages', [], 'ru'))
        ->toBe('Закрепленные сообщения');
});
