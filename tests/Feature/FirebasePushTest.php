<?php

use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\ChatMessage;
use App\Models\MobileDevice;
use App\Models\User;
use App\Notifications\ChatMessagePushNotification;
use App\Observers\ChatMessageObserver;
use App\Support\FirebaseAccessTokenProvider;
use App\Support\FirebaseCloudMessagingClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

test('FCM client sends a high priority notification and native deep link payload', function () {
    config(['services.fcm.project_id' => 'crm369-test-project']);

    $provider = Mockery::mock(FirebaseAccessTokenProvider::class);
    $provider->shouldReceive('isConfigured')->once()->andReturnTrue();
    $provider->shouldReceive('projectId')->once()->andReturn('crm369-test-project');
    $provider->shouldReceive('accessToken')->once()->andReturn('oauth-access-token');

    Http::fake([
        'https://fcm.googleapis.com/*' => Http::response(['name' => 'projects/test/messages/1']),
    ]);

    $device = MobileDevice::factory()->create(['fcm_token' => 'registered-fcm-token']);
    $client = new FirebaseCloudMessagingClient($provider);

    expect($client->send($device, [
        'title' => 'Новая задача',
        'body' => 'Проверьте срок выполнения',
        'type' => 'system',
        'action_path' => '/projects?task=42',
        'entity_id' => 42,
    ]))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer oauth-access-token')
        && $request['message']['token'] === 'registered-fcm-token'
        && $request['message']['android']['priority'] === 'HIGH'
        && $request['message']['data']['action_path'] === '/projects?task=42'
        && $request['message']['data']['entity_id'] === '42');
});

test('FCM client disables an unregistered device token', function () {
    config(['services.fcm.project_id' => 'crm369-test-project']);

    $provider = Mockery::mock(FirebaseAccessTokenProvider::class);
    $provider->shouldReceive('isConfigured')->once()->andReturnTrue();
    $provider->shouldReceive('projectId')->once()->andReturn('crm369-test-project');
    $provider->shouldReceive('accessToken')->once()->andReturn('oauth-access-token');

    Http::fake([
        'https://fcm.googleapis.com/*' => Http::response([
            'error' => [
                'code' => 404,
                'details' => [['errorCode' => 'UNREGISTERED']],
            ],
        ], 404),
    ]);

    $device = MobileDevice::factory()->create();
    $client = new FirebaseCloudMessagingClient($provider);

    expect($client->send($device, [
        'title' => 'Test',
        'body' => 'Test',
        'type' => 'system',
    ]))->toBeFalse()
        ->and($device->fresh()->disabled_at)->not->toBeNull();
});

test('FCM client exposes transient server errors to the notification queue', function () {
    config(['services.fcm.project_id' => 'crm369-test-project']);

    $provider = Mockery::mock(FirebaseAccessTokenProvider::class);
    $provider->shouldReceive('isConfigured')->once()->andReturnTrue();
    $provider->shouldReceive('projectId')->once()->andReturn('crm369-test-project');
    $provider->shouldReceive('accessToken')->once()->andReturn('oauth-access-token');

    Http::fake([
        'https://fcm.googleapis.com/*' => Http::response(['error' => ['code' => 500]], 500),
    ]);

    $device = MobileDevice::factory()->create();
    $client = new FirebaseCloudMessagingClient($provider);

    expect(fn (): bool => $client->send($device, [
        'title' => 'Test',
        'body' => 'Test',
        'type' => 'system',
    ]))->toThrow(RequestException::class);
});

test('chat messages queue push notifications for participants except the sender', function () {
    Notification::fake();

    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = ChatConversation::factory()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $sender->id,
    ]);

    ChatConversationParticipant::factory()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $sender->id,
    ]);
    ChatConversationParticipant::factory()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
    ]);

    $message = ChatMessage::withoutEvents(fn () => ChatMessage::factory()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $sender->id,
        'body' => 'Сообщение из нативного чата',
    ]));

    app(ChatMessageObserver::class)->created($message);

    Notification::assertSentTo(
        $recipient,
        ChatMessagePushNotification::class,
        fn (ChatMessagePushNotification $notification): bool => $notification->toFirebase($recipient)['entity_id'] === $conversation->id,
    );
    Notification::assertNotSentTo($sender, ChatMessagePushNotification::class);
});
