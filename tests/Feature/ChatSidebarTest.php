<?php

use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\ChatMessage;
use App\Models\User;
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

test('users can start a direct chat and load it in the sidebar payload', function () {
    $user = User::factory()->create();
    $recipient = User::factory()->create();

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
        ->assertJsonPath('activeConversation.title', trim($recipient->name.' '.($recipient->last_name ?? '')));
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
    $emojiPicker = file_get_contents(resource_path('js/components/ChatEmojiPicker.vue'));

    expect($header)->toContain('ChatCenterSheet')
        ->and($header)->toContain('MessageSquareMore')
        ->and($sidebar)->toContain('chatsIndex()')
        ->and($sidebar)->toContain("isMenuItemVisible('chats')")
        ->and($dock)->toContain('ChatCenterSheet')
        ->and($dock)->toContain('openChatCenter(\'chats\', entry.conversationId, entry.contactId)')
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
        ->and($panel)->toContain('initialConversationId')
        ->and($panel)->toContain('initialContactId')
        ->and($panel)->toContain('ChatEmojiPicker')
        ->and($panel)->toContain('@select="insertEmoji"')
        ->and($panel)->toContain('ref="draftTextarea"')
        ->and($panel)->toContain('body: JSON.stringify({ body: draft.value })')
        ->and($panel)->toContain('size="icon"')
        ->and($panel)->toContain('absolute right-3 bottom-3 size-10 rounded-full')
        ->and($panel)->toContain(':aria-label="sending ? t.chat.sending : t.chat.send"')
        ->and($panel)->toContain('pr-28')
        ->and($panel)->toContain('whitespace-pre-wrap')
        ->and($panel)->toContain('loadRequestedConversation')
        ->and($panel)->toContain("hour: '2-digit'")
        ->and($panel)->toContain("minute: '2-digit'")
        ->and($panel)->toContain("dateStyle: 'medium'")
        ->and($emojiPicker)->toContain('DropdownMenuTrigger')
        ->and($emojiPicker)->toContain("emit('select', emoji)")
        ->and($emojiPicker)->toContain('t.chat.emoji_picker_title')
        ->and($emojiPicker)->toContain('emojiOptions');
});
