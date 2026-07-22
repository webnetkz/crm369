<?php

use App\Models\ApiAccessToken;
use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\ChatMessage;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeBaseArticle;
use App\Models\MenuItem;
use App\Models\MessengerIntegration;
use App\Models\PortalSetting;
use App\Models\PortalWebhook;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Models\UserGroup;
use App\Notifications\SystemNotification;
use App\Support\ApiCatalog;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function apiAdministratorsGroup(): UserGroup
{
    return UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);
}

function issueApiTokenFor(User $user, array $permissions): string
{
    $plainTextToken = ApiAccessToken::generatePlainTextToken();

    ApiAccessToken::query()->create([
        'user_id' => $user->id,
        'name' => 'Test token',
        ...ApiAccessToken::tokenAttributes($plainTextToken),
        'permissions' => $permissions,
    ]);

    return $plainTextToken;
}

function apiHeadersFor(string $token): array
{
    return [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ];
}

test('api settings page is limited to admin-capable users and token issuance stays restricted', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('settings.api.edit'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('settings.api.tokens.store'), [
            'name' => 'Blocked token',
            'permissions' => [ApiAccessToken::PERMISSION_PROFILE_READ],
            'never_expires' => true,
        ])
        ->assertForbidden();

    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => apiAdministratorsGroup()->id,
    ]);

    $this->actingAs($admin)
        ->get(route('settings.api.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Api')
            ->where('can.manage_tokens', true)
            ->has('baseUrl')
            ->has('permissions')
            ->where('tokens', [])
        );

    $this->actingAs($admin)
        ->post(route('settings.api.tokens.store'), [
            'name' => 'Integration token',
            'permissions' => [
                ApiAccessToken::PERMISSION_PROFILE_READ,
                ApiAccessToken::PERMISSION_MENU_WRITE,
            ],
            'never_expires' => true,
        ])
        ->assertRedirect();

    $token = ApiAccessToken::query()->where('user_id', $admin->id)->first();

    expect($token)->not->toBeNull()
        ->and($token->resolvedPermissions())->toBe([
            ApiAccessToken::PERMISSION_PROFILE_READ,
            ApiAccessToken::PERMISSION_MENU_WRITE,
        ]);
});

test('api documentation page is visible to admin-capable users', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => apiAdministratorsGroup()->id,
    ]);

    $this->actingAs($admin)
        ->get(route('settings.api.documentation.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ApiDocumentation')
            ->has('baseUrl')
            ->has('documentation')
        );
});

test('admin can create api token without expiration date and it becomes never expiring', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => apiAdministratorsGroup()->id,
    ]);

    $this->actingAs($admin)
        ->post(route('settings.api.tokens.store'), [
            'name' => 'Permanent token',
            'permissions' => [ApiAccessToken::PERMISSION_PROFILE_READ],
            'expires_at' => null,
        ])
        ->assertRedirect();

    $token = ApiAccessToken::query()
        ->where('user_id', $admin->id)
        ->where('name', 'Permanent token')
        ->first();

    expect($token)->not->toBeNull()
        ->and($token->expires_at)->toBeNull();
});

test('admin can create api token when permissions arrive as checkbox-style keyed values', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => apiAdministratorsGroup()->id,
    ]);

    $this->actingAs($admin)
        ->post(route('settings.api.tokens.store'), [
            'name' => 'Checkbox token',
            'permissions' => [
                ApiAccessToken::PERMISSION_PROFILE_READ => 'on',
                ApiAccessToken::PERMISSION_MENU_WRITE => '1',
            ],
        ])
        ->assertRedirect();

    $token = ApiAccessToken::query()
        ->where('user_id', $admin->id)
        ->where('name', 'Checkbox token')
        ->first();

    expect($token)->not->toBeNull()
        ->and($token->resolvedPermissions())->toBe([
            ApiAccessToken::PERMISSION_PROFILE_READ,
            ApiAccessToken::PERMISSION_MENU_WRITE,
        ]);
});

test('admin can create api token when generated token prefix collides', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => apiAdministratorsGroup()->id,
    ]);

    $firstRandomPart = str_repeat('a', 64);
    $secondRandomPart = str_repeat('b', 64);
    $collidingToken = 'crm369_pat_'.$firstRandomPart;

    ApiAccessToken::query()->create([
        'user_id' => $admin->id,
        'name' => 'Existing token',
        ...ApiAccessToken::tokenAttributes($collidingToken),
        'permissions' => [ApiAccessToken::PERMISSION_PROFILE_READ],
    ]);

    Str::createRandomStringsUsingSequence([
        $firstRandomPart,
        $secondRandomPart,
    ]);

    try {
        $this->actingAs($admin)
            ->post(route('settings.api.tokens.store'), [
                'name' => 'Retried token',
                'permissions' => [ApiAccessToken::PERMISSION_PROFILE_READ],
                'never_expires' => true,
            ])
            ->assertRedirect();
    } finally {
        Str::createRandomStringsNormally();
    }

    $token = ApiAccessToken::query()
        ->where('user_id', $admin->id)
        ->where('name', 'Retried token')
        ->first();

    expect($token)->not->toBeNull()
        ->and($token->token_prefix)->not->toBe(ApiAccessToken::tokenAttributes($collidingToken)['token_prefix'])
        ->and(ApiAccessToken::query()->where('user_id', $admin->id)->count())->toBe(2);
});

test('admin can create api token with explicit expiration date', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => apiAdministratorsGroup()->id,
    ]);
    $expiresAt = now()->addDay()->setTime(15, 30);

    $this->actingAs($admin)
        ->post(route('settings.api.tokens.store'), [
            'name' => 'Expiring token',
            'permissions' => [ApiAccessToken::PERMISSION_PROFILE_READ],
            'expires_at' => $expiresAt->format('Y-m-d\TH:i'),
        ])
        ->assertRedirect();

    $token = ApiAccessToken::query()
        ->where('user_id', $admin->id)
        ->where('name', 'Expiring token')
        ->first();

    expect($token)->not->toBeNull()
        ->and($token->expires_at?->format('Y-m-d\TH:i'))->toBe($expiresAt->format('Y-m-d\TH:i'));
});

test('api tokens authenticate only admin-capable verified users and respect scopes', function () {
    $otherUser = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => apiAdministratorsGroup()->id,
    ]);

    $profileOnlyToken = issueApiTokenFor($admin, [
        ApiAccessToken::PERMISSION_PROFILE_READ,
    ]);

    $this->withHeaders(apiHeadersFor($profileOnlyToken))
        ->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.email', $admin->email);

    $this->withHeaders(apiHeadersFor($profileOnlyToken))
        ->postJson('/api/v1/menu/items', [
            'title' => 'Forbidden item',
            'url' => '/dashboard',
            'opens_in_new_tab' => false,
            'is_visible' => true,
            'is_global' => false,
        ])
        ->assertForbidden();

    $fullAdminToken = issueApiTokenFor($admin, [
        ApiAccessToken::PERMISSION_PROFILE_READ,
        ApiAccessToken::PERMISSION_PROFILE_WRITE,
        ApiAccessToken::PERMISSION_MENU_WRITE,
        ApiAccessToken::PERMISSION_USERS_READ,
    ]);

    $this->withHeaders(apiHeadersFor($fullAdminToken))
        ->patchJson('/api/v1/profile/language', [
            'language' => 'en',
        ])
        ->assertOk()
        ->assertJsonPath('data.language', 'en');

    expect($admin->refresh()->language)->toBe('en')
        ->and($admin->has_selected_language)->toBeTrue();

    $createMenuItemResponse = $this->withHeaders(apiHeadersFor($fullAdminToken))
        ->postJson('/api/v1/menu/items', [
            'title' => 'Portal docs',
            'icon' => 'rocket',
            'url' => '/knowledge-bases',
            'opens_in_new_tab' => false,
            'is_visible' => true,
            'is_global' => false,
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Portal docs')
        ->assertJsonPath('data.icon', 'rocket');

    $createdMenuItemId = $createMenuItemResponse->json('data.id');

    $this->withHeaders(apiHeadersFor($fullAdminToken))
        ->patchJson('/api/v1/menu/items/'.$createdMenuItemId, [
            'title' => 'Portal docs updated',
            'icon' => 'shield',
            'url' => '/settings/api',
            'opens_in_new_tab' => true,
            'is_visible' => true,
            'is_global' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Portal docs updated')
        ->assertJsonPath('data.icon', 'shield')
        ->assertJsonPath('data.opens_in_new_tab', true);

    $this->withHeaders(apiHeadersFor($fullAdminToken))
        ->getJson('/api/v1/users')
        ->assertOk();

    $regularUser = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $regularUserToken = issueApiTokenFor($regularUser, [
        ApiAccessToken::PERMISSION_PROFILE_READ,
    ]);

    $this->withHeaders(apiHeadersFor($regularUserToken))
        ->getJson('/api/v1/profile')
        ->assertUnauthorized();
});

test('legacy api tokens that use a plain text prefix still authenticate', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => apiAdministratorsGroup()->id,
    ]);

    $plainTextToken = ApiAccessToken::generatePlainTextToken();

    ApiAccessToken::query()->create([
        'user_id' => $admin->id,
        'name' => 'Legacy token',
        'token_prefix' => Str::substr($plainTextToken, 0, ApiAccessToken::TOKEN_PREFIX_LENGTH),
        'token_hash' => ApiAccessToken::hashToken($plainTextToken),
        'permissions' => [ApiAccessToken::PERMISSION_PROFILE_READ],
    ]);

    $this->withHeaders(apiHeadersFor($plainTextToken))
        ->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.email', $admin->email);
});

test('profile api exposes and updates position', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => apiAdministratorsGroup()->id,
        'position' => 'Operations Manager',
        'middle_name' => 'Kanatovna',
    ]);

    $token = issueApiTokenFor($admin, [
        ApiAccessToken::PERMISSION_PROFILE_READ,
        ApiAccessToken::PERMISSION_PROFILE_WRITE,
    ]);

    $this->withHeaders(apiHeadersFor($token))
        ->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.middle_name', 'Kanatovna')
        ->assertJsonPath('data.position', 'Operations Manager');

    $this->withHeaders(apiHeadersFor($token))
        ->patchJson('/api/v1/profile', [
            'name' => $admin->name,
            'email' => $admin->email,
            'phone' => '+7 777 123 45 67',
            'middle_name' => 'Samatovna',
            'position' => 'Commercial Director',
        ])
        ->assertOk()
        ->assertJsonPath('data.middle_name', 'Samatovna')
        ->assertJsonPath('data.position', 'Commercial Director');

    expect($admin->refresh()->position)->toBe('Commercial Director')
        ->and($admin->middle_name)->toBe('Samatovna')
        ->and($admin->phone)->toBe('+77771234567');
});

test('super admin token can read the documented api modules', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
        'email_verified_at' => now(),
    ]);

    $otherUser = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    PortalSetting::current()->update([
        'company_name' => 'CRM369',
        'default_language' => 'ru',
    ]);

    $knowledgeBase = KnowledgeBase::query()->create([
        'title' => 'API Handbook',
        'slug' => 'api-handbook',
        'description' => 'Docs',
        'is_published' => true,
        'created_by_user_id' => $superAdmin->id,
        'updated_by_user_id' => $superAdmin->id,
    ]);

    $article = KnowledgeBaseArticle::query()->create([
        'knowledge_base_id' => $knowledgeBase->id,
        'title' => 'Getting started',
        'slug' => 'getting-started',
        'excerpt' => 'First steps',
        'blocks' => [
            [
                'type' => KnowledgeBaseArticle::BLOCK_PARAGRAPH,
                'content' => 'Read this first.',
            ],
        ],
        'sort_order' => 1,
        'is_published' => true,
        'created_by_user_id' => $superAdmin->id,
        'updated_by_user_id' => $superAdmin->id,
    ]);

    $project = Project::query()->create([
        'name' => 'API Project',
        'slug' => 'api-project',
        'description' => 'Project for API checks',
        'is_archived' => false,
        'owner_user_id' => $superAdmin->id,
        'created_by_user_id' => $superAdmin->id,
        'updated_by_user_id' => $superAdmin->id,
    ]);
    $project->members()->sync([$superAdmin->id, $otherUser->id]);

    $task = ProjectTask::query()->create([
        'project_id' => $project->id,
        'parent_task_id' => null,
        'creator_user_id' => $superAdmin->id,
        'assignee_user_id' => $otherUser->id,
        'title' => 'Ship API',
        'description' => 'Implement endpoints',
        'status' => ProjectTask::STATUS_TODO,
        'importance' => ProjectTask::IMPORTANCE_HIGH,
        'complexity' => 7,
        'sort_order' => 1,
        'updated_by_user_id' => $superAdmin->id,
    ]);

    MenuItem::query()->create([
        'type' => MenuItem::TYPE_CUSTOM,
        'user_id' => $superAdmin->id,
        'is_global' => false,
        'title' => 'API shortcut',
        'icon' => 'book',
        'url' => '/settings/api',
        'opens_in_new_tab' => false,
        'is_visible' => true,
        'sort_order' => 10,
    ]);

    MessengerIntegration::query()->updateOrCreate(
        ['driver' => MessengerIntegration::DRIVER_TELEGRAM],
        [
            'name' => 'Telegram Bot',
            'is_active' => true,
            'settings' => ['bot_username' => 'crm369_bot'],
            'updated_by_user_id' => $superAdmin->id,
        ],
    );

    MessengerIntegration::query()->updateOrCreate(
        ['driver' => MessengerIntegration::DRIVER_TELEPHONY],
        [
            'name' => 'Telephony',
            'is_active' => true,
            'settings' => ['provider_name' => 'Binotel'],
            'updated_by_user_id' => $superAdmin->id,
        ],
    );

    PortalWebhook::query()->create([
        'name' => 'Portal Sync',
        'token_prefix' => 'sync-prefix',
        'token_hash' => hash('sha256', 'sync-token'),
        'permissions' => [PortalWebhook::PERMISSION_USERS_READ],
        'is_active' => true,
        'created_by_user_id' => $superAdmin->id,
    ]);

    $superAdmin->notify(new SystemNotification(
        title: 'API ready',
        message: 'Documentation is available.',
        actionUrl: '/settings/api',
        actionLabel: 'Open API',
    ));

    $conversation = ChatConversation::query()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $superAdmin->id,
        'last_message_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $superAdmin->id,
        'last_read_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $otherUser->id,
        'last_read_at' => now(),
    ]);

    ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $otherUser->id,
        'body' => 'Ping from teammate',
    ]);

    $token = issueApiTokenFor($superAdmin, ApiAccessToken::availablePermissions());
    $headers = apiHeadersFor($token);

    $this->withHeaders($headers)
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('meta.status', 'all')
        ->assertJsonPath('data.0.title', 'API ready');

    $readNotification = $superAdmin->notifications()->firstOrFail();
    $readNotification->markAsRead();

    $superAdmin->notify(new SystemNotification(
        title: 'Unread API notification',
        message: 'Still unread.',
    ));

    $this->withHeaders($headers)
        ->getJson('/api/v1/notifications?status=unread')
        ->assertOk()
        ->assertJsonPath('meta.status', 'unread')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Unread API notification');

    $this->withHeaders($headers)
        ->getJson('/api/v1/notifications?status=read')
        ->assertOk()
        ->assertJsonPath('meta.status', 'read')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'API ready');

    $this->withHeaders($headers)
        ->getJson('/api/v1/chats?conversation='.$conversation->id)
        ->assertOk()
        ->assertJsonPath('activeConversation.id', $conversation->id);

    $this->withHeaders($headers)
        ->getJson('/api/v1/knowledge-bases')
        ->assertOk()
        ->assertJsonPath('data.activeBase.id', $knowledgeBase->id);

    $this->withHeaders($headers)
        ->getJson('/api/v1/knowledge-bases/'.$knowledgeBase->id)
        ->assertOk()
        ->assertJsonPath('data.activeBase.id', $knowledgeBase->id);

    $this->withHeaders($headers)
        ->getJson('/api/v1/knowledge-bases/'.$knowledgeBase->id.'/articles/'.$article->id)
        ->assertOk()
        ->assertJsonPath('data.activeArticle.id', $article->id);

    $this->withHeaders($headers)
        ->getJson('/api/v1/projects')
        ->assertOk()
        ->assertJsonPath('data.projects.0.id', $project->id);

    $this->withHeaders($headers)
        ->getJson('/api/v1/projects/'.$project->id)
        ->assertOk()
        ->assertJsonPath('data.activeProject.id', $project->id);

    $this->withHeaders($headers)
        ->getJson('/api/v1/tasks/'.$task->id)
        ->assertOk()
        ->assertJsonPath('data.activeTask.id', $task->id);

    $this->withHeaders($headers)
        ->getJson('/api/v1/menu')
        ->assertOk()
        ->assertJsonPath('data.custom_items.0.title', 'API shortcut')
        ->assertJsonPath('data.custom_items.0.icon', 'book');

    $this->withHeaders($headers)
        ->getJson('/api/v1/groups')
        ->assertOk();

    $this->withHeaders($headers)
        ->getJson('/api/v1/portal')
        ->assertOk()
        ->assertJsonPath('data.company_name', 'CRM369');

    $integrationsResponse = $this->withHeaders($headers)
        ->getJson('/api/v1/integrations')
        ->assertOk();

    expect(collect($integrationsResponse->json('data'))->pluck('name')->all())
        ->toContain('Telegram Bot')
        ->toContain('Telephony');

    $this->withHeaders($headers)
        ->getJson('/api/v1/webhooks')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Portal Sync');
});

test('api task creation preserves the exact due time', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => apiAdministratorsGroup()->id,
    ]);
    $assignee = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $coAssignee = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $dueAt = now()->addDays(3)->setTime(16, 45);

    $token = issueApiTokenFor($admin, [
        ApiAccessToken::PERMISSION_TASKS_WRITE,
    ]);

    $response = $this->withHeaders(apiHeadersFor($token))
        ->postJson('/api/v1/tasks', [
            'project_id' => null,
            'parent_task_id' => null,
            'title' => 'API deadline check',
            'description' => 'Ensure time is preserved.',
            'status' => ProjectTask::STATUS_IN_PROGRESS,
            'importance' => ProjectTask::IMPORTANCE_HIGH,
            'complexity' => 6,
            'due_at' => $dueAt->toISOString(),
            'sort_order' => 2,
            'assignee_user_id' => $assignee->id,
            'co_assignee_user_ids' => [$coAssignee->id],
        ])
        ->assertCreated();

    $task = ProjectTask::query()->where('title', 'API deadline check')->firstOrFail();

    expect($task->due_at?->toISOString())->toBe($dueAt->toISOString())
        ->and($response->json('data.due_at'))->toBe($dueAt->toISOString())
        ->and($assignee->notifications()->count())->toBe(1)
        ->and($coAssignee->notifications()->count())->toBe(1);
});

test('super admin token can read and update user-scoped api data via the user_id query parameter', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
        'email_verified_at' => now(),
        'language' => 'ru',
        'has_selected_language' => true,
    ]);

    $subjectUser = User::factory()->create([
        'email_verified_at' => now(),
        'language' => 'ru',
        'has_selected_language' => true,
    ]);

    $contact = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $superAdmin->notify(new SystemNotification(
        title: 'Owner notification',
        message: 'Visible only to the token owner.',
    ));

    $subjectUser->notify(new SystemNotification(
        title: 'Subject notification',
        message: 'Visible only to the requested user.',
    ));

    $conversation = ChatConversation::query()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $subjectUser->id,
        'last_message_at' => now(),
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $subjectUser->id,
        'last_read_at' => null,
    ]);

    ChatConversationParticipant::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $contact->id,
        'last_read_at' => now(),
    ]);

    ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $contact->id,
        'body' => 'Ping for the subject user',
    ]);

    $project = Project::query()->create([
        'name' => 'Subject project',
        'slug' => 'subject-project',
        'description' => 'Project visible only in the requested user context.',
        'is_archived' => false,
        'owner_user_id' => $subjectUser->id,
        'created_by_user_id' => $subjectUser->id,
        'updated_by_user_id' => $subjectUser->id,
    ]);
    $project->members()->sync([$subjectUser->id, $contact->id]);

    MenuItem::query()->create([
        'type' => MenuItem::TYPE_CUSTOM,
        'user_id' => $subjectUser->id,
        'is_global' => false,
        'title' => 'Subject shortcut',
        'icon' => 'book',
        'url' => '/projects',
        'opens_in_new_tab' => false,
        'is_visible' => true,
        'sort_order' => 10,
    ]);

    $token = issueApiTokenFor($superAdmin, [
        ApiAccessToken::PERMISSION_PROFILE_READ,
        ApiAccessToken::PERMISSION_PROFILE_WRITE,
        ApiAccessToken::PERMISSION_NOTIFICATIONS_READ,
        ApiAccessToken::PERMISSION_NOTIFICATIONS_WRITE,
        ApiAccessToken::PERMISSION_CHAT_READ,
        ApiAccessToken::PERMISSION_PROJECTS_READ,
        ApiAccessToken::PERMISSION_MENU_READ,
        ApiAccessToken::PERMISSION_MENU_WRITE,
    ]);

    $headers = apiHeadersFor($token);
    $subjectQuery = '?user_id='.$subjectUser->id;

    $this->withHeaders($headers)
        ->getJson('/api/v1/profile'.$subjectQuery)
        ->assertOk()
        ->assertJsonPath('data.id', $subjectUser->id)
        ->assertJsonPath('data.email', $subjectUser->email);

    $this->withHeaders($headers)
        ->patchJson('/api/v1/profile/language'.$subjectQuery, [
            'language' => 'en',
        ])
        ->assertOk()
        ->assertJsonPath('data.id', $subjectUser->id)
        ->assertJsonPath('data.language', 'en');

    expect($subjectUser->refresh()->language)->toBe('en')
        ->and($subjectUser->has_selected_language)->toBeTrue()
        ->and($superAdmin->refresh()->language)->toBe('ru');

    $this->withHeaders($headers)
        ->getJson('/api/v1/notifications'.$subjectQuery)
        ->assertOk()
        ->assertJsonPath('meta.status', 'all')
        ->assertJsonPath('meta.subject_user_id', $subjectUser->id)
        ->assertJsonPath('data.0.title', 'Subject notification');

    $readSubjectNotification = $subjectUser->notifications()->latest('created_at')->firstOrFail();
    $readSubjectNotification->markAsRead();

    $subjectUser->notify(new SystemNotification(
        title: 'Unread subject notification',
        message: 'Visible only in unread filter.',
    ));

    $this->withHeaders($headers)
        ->getJson('/api/v1/notifications'.$subjectQuery.'&status=unread')
        ->assertOk()
        ->assertJsonPath('meta.status', 'unread')
        ->assertJsonPath('meta.subject_user_id', $subjectUser->id)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Unread subject notification');

    $this->withHeaders($headers)
        ->getJson('/api/v1/notifications'.$subjectQuery.'&status=read')
        ->assertOk()
        ->assertJsonPath('meta.status', 'read')
        ->assertJsonPath('meta.subject_user_id', $subjectUser->id)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Subject notification');

    $subjectNotification = $subjectUser->unreadNotifications()
        ->latest('created_at')
        ->firstOrFail();

    $this->withHeaders($headers)
        ->patchJson('/api/v1/notifications/'.$subjectNotification->id.'/read'.$subjectQuery)
        ->assertOk()
        ->assertJsonPath('meta.subject_user_id', $subjectUser->id)
        ->assertJsonPath('data.title', 'Unread subject notification')
        ->assertJsonPath('meta.unread_count', 0);

    expect($subjectNotification->refresh()->read_at)->not->toBeNull()
        ->and($superAdmin->refresh()->unreadNotifications()->count())->toBe(1);

    $subjectUser->notify(new SystemNotification(
        title: 'Second subject notification',
        message: 'Needs bulk mark as read.',
    ));

    $this->withHeaders($headers)
        ->patchJson('/api/v1/notifications/read-all'.$subjectQuery)
        ->assertOk()
        ->assertJsonPath('meta.subject_user_id', $subjectUser->id)
        ->assertJsonPath('meta.unread_count', 0);

    expect($subjectUser->refresh()->unreadNotifications()->count())->toBe(0)
        ->and($superAdmin->refresh()->unreadNotifications()->count())->toBe(1);

    $this->withHeaders($headers)
        ->getJson('/api/v1/chats'.$subjectQuery.'&conversation='.$conversation->id)
        ->assertOk()
        ->assertJsonPath('activeConversation.id', $conversation->id)
        ->assertJsonPath('activeConversation.messages.0.body', 'Ping for the subject user');

    $this->withHeaders($headers)
        ->getJson('/api/v1/projects'.$subjectQuery)
        ->assertOk()
        ->assertJsonPath('data.projects.0.id', $project->id);

    $this->withHeaders($headers)
        ->getJson('/api/v1/menu'.$subjectQuery)
        ->assertOk()
        ->assertJsonPath('data.custom_items.0.title', 'Subject shortcut');

    $menuCreateResponse = $this->withHeaders($headers)
        ->postJson('/api/v1/menu/items'.$subjectQuery, [
            'title' => 'Created for subject',
            'icon' => 'rocket',
            'url' => '/settings/api',
            'opens_in_new_tab' => false,
            'is_visible' => true,
            'is_global' => false,
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Created for subject');

    expect(MenuItem::query()->findOrFail($menuCreateResponse->json('data.id'))->user_id)
        ->toBe($subjectUser->id);
});

test('api user_id query requires impersonation permission', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => apiAdministratorsGroup()->id,
    ]);

    $subjectUser = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $token = issueApiTokenFor($admin, [
        ApiAccessToken::PERMISSION_PROFILE_READ,
    ]);

    $this->withHeaders(apiHeadersFor($token))
        ->getJson('/api/v1/profile?user_id='.$subjectUser->id)
        ->assertForbidden();
});

test('users api ignores the user_id query on endpoints that do not support subject context', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'user_group_id' => apiAdministratorsGroup()->id,
    ]);

    $subjectUser = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $token = issueApiTokenFor($admin, [
        ApiAccessToken::PERMISSION_USERS_READ,
    ]);

    $this->withHeaders(apiHeadersFor($token))
        ->getJson('/api/v1/users?user_id='.$subjectUser->id)
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'meta',
            'filters',
            'groups',
            'per_page_options',
        ]);
});

test('api documentation marks which endpoints support the user_id query parameter', function () {
    $sections = app(ApiCatalog::class)->sections();

    $profileEndpoint = collect($sections)
        ->firstWhere('title', __('ui.api.section_profile'))['endpoints'][0];

    $notificationsSection = collect($sections)
        ->firstWhere('title', __('ui.api.section_notifications'));

    $usersEndpoint = collect($sections)
        ->firstWhere('title', __('ui.api.section_users'))['endpoints'][0];

    expect($profileEndpoint['target_user'])->toBe(__('ui.api.target_user_supported'))
        ->and($notificationsSection['notes'])->toContain(__('ui.api.section_notifications_note'))
        ->and($usersEndpoint['target_user'])->toBe(__('ui.api.target_user_not_supported'));
});

test('api token settings form keeps selected permissions in the posted payload', function () {
    $apiPage = file_get_contents(resource_path('js/pages/settings/Api.vue'));
    $apiDocumentationPage = file_get_contents(resource_path('js/pages/settings/ApiDocumentation.vue'));

    expect($apiPage)->toContain('never_expires: true')
        ->and($apiPage)->toContain('issuedTokenDialogOpen')
        ->and($apiPage)->toContain('const readFlashApiToken = (): IssuedApiToken =>')
        ->and($apiPage)->toContain('const applyIssuedToken = (token: IssuedApiToken): void =>')
        ->and($apiPage)->toContain('aria-label="API sections"')
        ->and($apiPage)->toContain("const createTokenSectionId = 'api-create-token'")
        ->and($apiPage)->toContain("const tokensSectionId = 'api-tokens'")
        ->and($apiPage)->toContain(':href="`#${section.id}`"')
        ->and($apiPage)->toContain('class="rounded-2xl border border-border bg-background/95 p-4 shadow-sm supports-[backdrop-filter]:bg-background/80 supports-[backdrop-filter]:backdrop-blur"')
        ->and($apiPage)->toContain('DialogContent class="sm:max-w-lg"')
        ->and($apiPage)->toContain("form.never_expires = form.expires_at.trim() === ''")
        ->and($apiPage)->toContain('form.permissions = togglePermission(')
        ->and($apiPage)->toContain('onFlash: (flash: { apiToken?: IssuedApiToken }) =>')
        ->and($apiPage)->toContain('permissions: [] as string[]')
        ->and($apiPage)->not->toContain(':disabled="form.never_expires"')
        ->and($apiPage)->not->toContain('apiDocumentationUrl')
        ->and($apiPage)->not->toContain('t.settings.api_documentation')
        ->and($apiPage)->not->toContain("const documentationSectionId = 'api-documentation'")
        ->and($apiDocumentationPage)->toContain("const documentationSectionId = 'api-documentation'")
        ->and($apiDocumentationPage)->toContain('{{ t.api.target_user_overview }}')
        ->and($apiDocumentationPage)->toContain('{{ t.api.target_user }}')
        ->and($apiDocumentationPage)->toContain('{{ endpoint.target_user }}')
        ->and($apiDocumentationPage)->toContain('v-if="section.notes.length > 0"')
        ->and($apiDocumentationPage)->toContain('v-for="note in section.notes"')
        ->and($apiDocumentationPage)->toContain('const documentationSectionAnchorId = (index: number): string =>')
        ->and($apiDocumentationPage)->toContain('const sectionCardClass = (title: string): string =>')
        ->and($apiDocumentationPage)->toContain('title === t.value.api.section_equipment')
        ->and($apiDocumentationPage)->toContain("'scroll-mt-40 space-y-3 rounded-2xl border border-border p-4'")
        ->and($apiDocumentationPage)->toContain('const documentationNavigationSections = computed(() =>')
        ->and($apiDocumentationPage)->toContain('class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_18rem] xl:items-start xl:gap-6"')
        ->and($apiDocumentationPage)->toContain("'scroll-mt-32 space-y-3 rounded-2xl border border-border p-4'")
        ->and($apiDocumentationPage)->toContain('class="hidden xl:block xl:sticky xl:top-32"')
        ->and($apiDocumentationPage)->not->toContain('class="sticky top-24 z-10 rounded-2xl border border-border bg-background/95 p-4 shadow-sm supports-[backdrop-filter]:bg-background/80 supports-[backdrop-filter]:backdrop-blur"')
        ->and($apiDocumentationPage)->not->toContain('IntersectionObserver')
        ->and($apiDocumentationPage)->not->toContain('v-show="documentationNavigationPinned"')
        ->and($apiDocumentationPage)->not->toContain('fixed top-24 right-4 z-20 w-72')
        ->and($apiDocumentationPage)->toContain('<details')
        ->and($apiDocumentationPage)->toContain('{{ t.api.documentation_blocks }}');
});

test('checkbox component supports checked-style bindings used by settings forms', function () {
    $checkboxComponent = file_get_contents(resource_path('js/components/ui/checkbox/Checkbox.vue'));

    expect($checkboxComponent)->toContain('checked?: CheckboxValue')
        ->and($checkboxComponent)->toContain('update:checked')
        ->and($checkboxComponent)->toContain('checked === undefined ? modelValue : checked');
});
