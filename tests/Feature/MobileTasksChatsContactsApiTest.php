<?php

use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\ChatMessageRead;
use App\Models\Contact;
use App\Models\ContactComment;
use App\Models\MobileAccessToken;
use App\Models\PortalSetting;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskStage;
use App\Models\User;
use App\Models\UserGroup;
use App\Notifications\SystemNotification;
use App\Support\ProjectPageData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Issue a mobile bearer token for the user. The resolved mobile user is cached by the guard
 * between requests of one test, so the guards are reset whenever another user signs in.
 */
function mobileTasksChatsContactsToken(User $user): string
{
    app('auth')->forgetGuards();

    return MobileAccessToken::issueToUser($user, 'android-tasks-chats-contacts-'.$user->id)['plain_text_token'];
}

function mobileTasksChatsContactsSuperAdmin(): User
{
    config(['admin.super_admin_email' => 'mobile-tasks-chats-contacts-admin@example.com']);

    return User::factory()->create([
        'email' => 'mobile-tasks-chats-contacts-admin@example.com',
        'language' => 'en',
    ]);
}

/**
 * @param  array<int, string>  $permissions
 * @param  array<int, string>  $configuredModules
 */
function mobileTasksChatsContactsGroupUser(array $permissions = [], array $configuredModules = []): User
{
    $group = UserGroup::factory()->create([
        'permissions' => UserGroup::normalizePermissionsWithConfiguredModules($permissions, $configuredModules),
    ]);

    return User::factory()->create([
        'user_group_id' => $group->id,
        'language' => 'en',
    ]);
}

function mobileTasksChatsContactsDirectConversation(User $first, User $second): ChatConversation
{
    $conversation = ChatConversation::query()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $first->id,
        'last_message_at' => now(),
    ]);

    foreach ([$first, $second] as $participant) {
        ChatConversationParticipant::query()->create([
            'chat_conversation_id' => $conversation->id,
            'user_id' => $participant->id,
            'last_read_at' => null,
        ]);
    }

    return $conversation;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function mobileTasksChatsContactsStandaloneTask(User $creator, array $attributes = []): ProjectTask
{
    return ProjectTask::factory()->standalone()->create([
        'creator_user_id' => $creator->id,
        'assignee_user_id' => null,
        'updated_by_user_id' => $creator->id,
        'status' => ProjectTask::STATUS_TODO,
        'completed_at' => null,
        ...$attributes,
    ]);
}

test('mobile task chat and contact endpoints require a mobile session', function () {
    foreach ([
        ['GET', route('mobile.v1.tasks.index')],
        ['GET', route('mobile.v1.projects.workspace')],
        ['GET', route('mobile.v1.projects.index')],
        ['POST', route('mobile.v1.projects.store')],
        ['GET', route('mobile.v1.projects.show', 1)],
        ['PATCH', route('mobile.v1.projects.update', 1)],
        ['DELETE', route('mobile.v1.projects.destroy', 1)],
        ['POST', route('mobile.v1.tasks.store')],
        ['GET', route('mobile.v1.tasks.show', 1)],
        ['PATCH', route('mobile.v1.tasks.update', 1)],
        ['DELETE', route('mobile.v1.tasks.destroy', 1)],
        ['PATCH', route('mobile.v1.tasks.move', 1)],
        ['GET', route('mobile.v1.tasks.conversation.show', 1)],
        ['POST', route('mobile.v1.task-stages.store')],
        ['PATCH', route('mobile.v1.task-stages.move')],
        ['PATCH', route('mobile.v1.task-stages.update', 1)],
        ['GET', route('mobile.v1.chats.index')],
        ['POST', route('mobile.v1.chats.direct.store')],
        ['POST', route('mobile.v1.chats.messages.store', 1)],
        ['PATCH', route('mobile.v1.chats.messages.update', [1, 1])],
        ['DELETE', route('mobile.v1.chats.messages.destroy', [1, 1])],
        ['PATCH', route('mobile.v1.chats.messages.pin', [1, 1])],
        ['DELETE', route('mobile.v1.chats.messages.unpin', [1, 1])],
        ['GET', route('mobile.v1.chats.attachments.preview', 1)],
        ['GET', route('mobile.v1.chats.attachments.download', 1)],
        ['GET', route('mobile.v1.chats.users.show', 1)],
        ['GET', route('mobile.v1.contacts.index')],
        ['GET', route('mobile.v1.contacts.show', 1)],
        ['POST', route('mobile.v1.contacts.store')],
        ['PATCH', route('mobile.v1.contacts.update', 1)],
        ['DELETE', route('mobile.v1.contacts.destroy', 1)],
        ['GET', route('mobile.v1.contacts.comments.index', 1)],
        ['POST', route('mobile.v1.contacts.comments.store', 1)],
    ] as [$method, $url]) {
        $this->json($method, $url)
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    $this->get(route('mobile.v1.chats.attachments.download', 1))->assertUnauthorized();

    $this->withToken('crm369_mobile_'.str_repeat('x', 80))
        ->getJson(route('mobile.v1.tasks.index'))
        ->assertUnauthorized();

    app('auth')->forgetGuards();

    $this->withToken('')
        ->actingAs(User::factory()->create(), 'web')
        ->getJson(route('mobile.v1.projects.workspace'))
        ->assertUnauthorized();

    expect(ContactComment::query()->exists())->toBeFalse();
});

test('mobile task chat and contact endpoints are not found when their module is disabled', function () {
    $superAdmin = mobileTasksChatsContactsSuperAdmin();
    $recipient = User::factory()->create();
    $task = mobileTasksChatsContactsStandaloneTask($superAdmin, ['title' => 'Disabled module task']);
    $stage = ProjectTaskStage::query()->ordered()->firstOrFail();
    $conversation = mobileTasksChatsContactsDirectConversation($superAdmin, $recipient);
    $message = ChatMessage::factory()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
    ]);
    $attachment = ChatMessageAttachment::factory()->create(['chat_message_id' => $message->id]);
    $contact = Contact::factory()->person()->create();

    PortalSetting::current()->update(['disabled_modules' => ['projects', 'chats', 'contacts']]);

    $this->withToken(mobileTasksChatsContactsToken($superAdmin));

    $this->getJson(route('mobile.v1.tasks.index'))->assertNotFound();
    $this->getJson(route('mobile.v1.projects.workspace'))->assertNotFound();
    $this->getJson(route('mobile.v1.projects.index'))->assertNotFound();
    $this->postJson(route('mobile.v1.tasks.store'), ['title' => 'Hidden'])->assertNotFound();
    $this->patchJson(route('mobile.v1.tasks.move', $task), ['status' => ProjectTask::STATUS_DONE])->assertNotFound();
    $this->getJson(route('mobile.v1.tasks.conversation.show', $task))->assertNotFound();
    $this->postJson(route('mobile.v1.task-stages.store'), ['name' => 'Hidden', 'color' => '#000000'])->assertNotFound();
    $this->patchJson(route('mobile.v1.task-stages.move'), ['stage_ids' => [$stage->id]])->assertNotFound();
    $this->patchJson(route('mobile.v1.task-stages.update', $stage), ['name' => 'Hidden', 'color' => '#000000'])->assertNotFound();
    $this->getJson(route('mobile.v1.chats.index'))->assertNotFound();
    $this->patchJson(route('mobile.v1.chats.messages.pin', [$conversation, $message]))->assertNotFound();
    $this->deleteJson(route('mobile.v1.chats.messages.unpin', [$conversation, $message]))->assertNotFound();
    $this->getJson(route('mobile.v1.chats.attachments.preview', $attachment))->assertNotFound();
    $this->getJson(route('mobile.v1.chats.attachments.download', $attachment))->assertNotFound();
    $this->getJson(route('mobile.v1.chats.users.show', $recipient))->assertNotFound();
    $this->getJson(route('mobile.v1.contacts.index'))->assertNotFound();
    $this->getJson(route('mobile.v1.contacts.comments.index', $contact))->assertNotFound();
    $this->postJson(route('mobile.v1.contacts.comments.store', $contact), ['content' => 'Hidden'])
        ->assertNotFound()
        ->assertExactJson(['message' => 'Not Found']);

    expect($task->fresh()->status)->toBe(ProjectTask::STATUS_TODO)
        ->and($stage->fresh()->name)->not->toBe('Hidden')
        ->and(ProjectTaskStage::query()->where('name', 'Hidden')->exists())->toBeFalse()
        ->and($message->fresh()->isPinned())->toBeFalse()
        ->and(ContactComment::query()->exists())->toBeFalse()
        ->and($task->chatConversation()->exists())->toBeFalse();
});

test('mobile task chat and contact endpoints are forbidden without the module permissions', function () {
    $owner = User::factory()->create();
    $task = mobileTasksChatsContactsStandaloneTask($owner);
    $stage = ProjectTaskStage::query()->ordered()->firstOrFail();
    $restricted = mobileTasksChatsContactsGroupUser([], ['projects', 'chats', 'contacts']);
    $conversation = mobileTasksChatsContactsDirectConversation($owner, $restricted);
    $message = ChatMessage::factory()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $owner->id,
    ]);
    $attachment = ChatMessageAttachment::factory()->create(['chat_message_id' => $message->id]);
    $contact = Contact::factory()->person()->create();

    $this->withToken(mobileTasksChatsContactsToken($restricted));

    $this->getJson(route('mobile.v1.tasks.index'))->assertForbidden();
    $this->getJson(route('mobile.v1.projects.workspace'))->assertForbidden();
    $this->patchJson(route('mobile.v1.tasks.move', $task), ['status' => ProjectTask::STATUS_DONE])->assertForbidden();
    $this->getJson(route('mobile.v1.tasks.conversation.show', $task))->assertForbidden();
    $this->postJson(route('mobile.v1.task-stages.store'), ['name' => 'Hidden', 'color' => '#000000'])->assertForbidden();
    $this->patchJson(route('mobile.v1.task-stages.move'), ['stage_ids' => [$stage->id]])->assertForbidden();
    $this->patchJson(route('mobile.v1.task-stages.update', $stage), ['name' => 'Hidden', 'color' => '#000000'])->assertForbidden();
    $this->patchJson(route('mobile.v1.chats.messages.pin', [$conversation, $message]))->assertForbidden();
    $this->deleteJson(route('mobile.v1.chats.messages.unpin', [$conversation, $message]))->assertForbidden();
    $this->getJson(route('mobile.v1.chats.attachments.preview', $attachment))->assertForbidden();
    $this->getJson(route('mobile.v1.chats.attachments.download', $attachment))->assertForbidden();
    $this->getJson(route('mobile.v1.chats.users.show', $owner))->assertForbidden();
    $this->getJson(route('mobile.v1.contacts.comments.index', $contact))->assertForbidden();
    $this->postJson(route('mobile.v1.contacts.comments.store', $contact), ['content' => 'Hidden'])
        ->assertForbidden();

    expect($task->fresh()->status)->toBe(ProjectTask::STATUS_TODO)
        ->and(ProjectTaskStage::query()->where('name', 'Hidden')->exists())->toBeFalse()
        ->and($message->fresh()->isPinned())->toBeFalse()
        ->and(ContactComment::query()->exists())->toBeFalse();
});

test('the mobile tasks page mirrors the web tasks page and remembers the requested view', function () {
    $user = User::factory()->create(['language' => 'en', 'task_display_mode' => ProjectPageData::VIEW_LIST]);
    $teammate = User::factory()->create(['language' => 'en']);
    $project = Project::factory()->create([
        'owner_user_id' => $user->id,
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
    ]);
    $project->members()->sync([$user->id]);

    $standaloneTask = mobileTasksChatsContactsStandaloneTask($user, [
        'assignee_user_id' => $teammate->id,
        'title' => 'Inbox standalone',
    ]);
    $standaloneTask->coAssignees()->sync([$teammate->id]);
    $subtask = mobileTasksChatsContactsStandaloneTask($user, [
        'parent_task_id' => $standaloneTask->id,
        'title' => 'Standalone subtask',
        'status' => ProjectTask::STATUS_IN_PROGRESS,
    ]);
    ProjectTask::factory()->create([
        'project_id' => $project->id,
        'creator_user_id' => $user->id,
        'assignee_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        'title' => 'Project only task',
    ]);
    $foreignTask = ProjectTask::factory()->standalone()->create(['title' => 'Foreign standalone']);

    $this->withToken(mobileTasksChatsContactsToken($user));

    $response = $this->getJson(route('mobile.v1.tasks.index', ['view' => ProjectPageData::VIEW_KANBAN]))
        ->assertSuccessful()
        ->assertJsonPath('data.pageMode', ProjectPageData::MODE_TASKS)
        ->assertJsonPath('data.taskDisplayMode', ProjectPageData::VIEW_KANBAN)
        ->assertJsonPath('data.activeProject', null)
        ->assertJsonPath('data.activeTask', null)
        ->assertJsonCount(1, 'data.taskGroups')
        ->assertJsonPath('data.taskGroups.0.kind', 'standalone')
        ->assertJsonPath('data.taskGroups.0.project', null)
        ->assertJsonPath('data.taskGroups.0.tasks_count', 2)
        ->assertJsonPath('data.taskGroups.0.open_tasks_count', 2)
        ->assertJsonCount(1, 'data.taskGroups.0.tasks')
        ->assertJsonPath('data.taskGroups.0.tasks.0.id', $standaloneTask->id)
        ->assertJsonPath('data.taskGroups.0.tasks.0.assignee.id', $teammate->id)
        ->assertJsonPath('data.taskGroups.0.tasks.0.co_assignees_count', 1)
        ->assertJsonPath('data.taskGroups.0.tasks.0.subtasks_count', 1)
        ->assertJsonPath('data.taskGroups.0.tasks.0.subtasks.0.id', $subtask->id)
        ->assertJsonPath('data.taskGroups.0.tasks.0.subtasks.0.parent_task_title', 'Inbox standalone')
        ->assertJsonCount(4, 'data.taskOptions.statuses')
        ->assertJsonPath('data.taskOptions.statuses.0.value', ProjectTaskStage::SLUG_TODO)
        ->assertJsonPath('data.taskOptions.statuses.3.value', ProjectTaskStage::SLUG_DONE)
        ->assertJsonPath('data.taskOptions.statuses.3.is_completed', true)
        ->assertJsonPath('data.taskOptions.complexity', range(1, 10))
        ->assertJsonPath('data.can.manageTaskStages', true)
        ->assertJsonPath('data.workspaceSummary.standalone_tasks_count', 2)
        ->assertJsonStructure([
            'data' => [
                'pageMode',
                'taskDisplayMode',
                'projects' => [['id', 'name', 'slug', 'description', 'is_archived', 'members_count', 'tasks_count', 'open_tasks_count', 'completed_tasks_count', 'updated_at', 'owner']],
                'taskGroups' => [[
                    'key', 'kind', 'title', 'description', 'project', 'tasks_count', 'open_tasks_count', 'completed_tasks_count',
                    'tasks' => [[
                        'id', 'project_id', 'project_name', 'parent_task_id', 'parent_task_title', 'title', 'status', 'importance',
                        'complexity', 'due_at', 'completed_at', 'created_at', 'creator', 'co_assignees_count', 'subtasks_count',
                        'updated_at', 'subtasks',
                        'assignee' => ['id', 'name', 'last_name', 'email', 'avatar', 'avatar_scale'],
                    ]],
                ]],
                'activeProject',
                'activeTask',
                'availableUsers' => [['id', 'name', 'last_name', 'email', 'avatar', 'avatar_scale']],
                'availableProjects' => [['id', 'name', 'is_archived', 'members']],
                'can' => ['createProject', 'createTask', 'manageTaskStages', 'manageProject', 'manageTask', 'workOnActiveProject'],
                'taskOptions' => [
                    'statuses' => [['id', 'value', 'label', 'color', 'is_completed', 'sort_order']],
                    'importances' => [['value', 'label']],
                    'complexity',
                ],
                'workspaceSummary' => ['standalone_tasks_count', 'standalone_open_tasks_count', 'standalone_completed_tasks_count'],
            ],
        ]);

    $taskTitles = collect($response->json('data.taskGroups.0.tasks'))->pluck('title');

    expect($user->fresh()->task_display_mode)->toBe(ProjectPageData::VIEW_KANBAN)
        ->and($taskTitles)->not->toContain('Project only task')
        ->and($taskTitles)->not->toContain($foreignTask->title)
        ->and($response->json('data'))->toEqual(json_decode(json_encode(app(ProjectPageData::class)->build(
            $user->fresh(),
            mode: ProjectPageData::MODE_TASKS,
            taskDisplayMode: ProjectPageData::VIEW_KANBAN,
        )), true));

    $this->getJson(route('mobile.v1.tasks.index'))
        ->assertSuccessful()
        ->assertJsonPath('data.taskDisplayMode', ProjectPageData::VIEW_KANBAN);

    $this->getJson(route('mobile.v1.tasks.index', ['view' => 'timeline']))
        ->assertSuccessful()
        ->assertJsonPath('data.taskDisplayMode', ProjectPageData::VIEW_KANBAN);

    $this->getJson(route('mobile.v1.tasks.index', ['view' => ProjectPageData::VIEW_GANTT]))
        ->assertSuccessful()
        ->assertJsonPath('data.taskDisplayMode', ProjectPageData::VIEW_GANTT);

    expect($user->fresh()->task_display_mode)->toBe(ProjectPageData::VIEW_GANTT);
});

test('the mobile projects workspace returns the web projects page props', function () {
    $owner = User::factory()->create(['language' => 'en']);
    $member = User::factory()->create(['language' => 'en']);
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
        'name' => 'Mobile rollout',
        'is_archived' => false,
    ]);
    $project->members()->sync([$owner->id, $member->id]);
    $hiddenProject = Project::factory()->create(['name' => 'Hidden project']);

    $projectTask = ProjectTask::factory()->create([
        'project_id' => $project->id,
        'creator_user_id' => $owner->id,
        'assignee_user_id' => $member->id,
        'updated_by_user_id' => $owner->id,
        'title' => 'Release checklist',
        'status' => ProjectTask::STATUS_REVIEW,
    ]);
    $standaloneTask = mobileTasksChatsContactsStandaloneTask($owner, ['title' => 'Personal follow-up']);

    $this->withToken(mobileTasksChatsContactsToken($owner));

    $response = $this->getJson(route('mobile.v1.projects.workspace'))
        ->assertSuccessful()
        ->assertJsonPath('data.pageMode', ProjectPageData::MODE_PROJECTS)
        ->assertJsonPath('data.taskDisplayMode', ProjectPageData::VIEW_LIST)
        ->assertJsonCount(1, 'data.projects')
        ->assertJsonPath('data.projects.0.id', $project->id)
        ->assertJsonPath('data.projects.0.members_count', 2)
        ->assertJsonPath('data.projects.0.tasks_count', 1)
        ->assertJsonPath('data.projects.0.open_tasks_count', 1)
        ->assertJsonPath('data.projects.0.owner.id', $owner->id)
        ->assertJsonCount(2, 'data.taskGroups')
        ->assertJsonPath('data.taskGroups.0.key', 'standalone')
        ->assertJsonPath('data.taskGroups.0.tasks.0.id', $standaloneTask->id)
        ->assertJsonPath('data.taskGroups.1.key', 'project-'.$project->id)
        ->assertJsonPath('data.taskGroups.1.kind', 'project')
        ->assertJsonPath('data.taskGroups.1.project.id', $project->id)
        ->assertJsonPath('data.taskGroups.1.project.owner.id', $owner->id)
        ->assertJsonPath('data.taskGroups.1.tasks.0.id', $projectTask->id)
        ->assertJsonPath('data.taskGroups.1.tasks.0.status', ProjectTask::STATUS_REVIEW)
        ->assertJsonPath('data.taskGroups.1.tasks.0.project_name', 'Mobile rollout')
        ->assertJsonPath('data.availableProjects.0.id', $project->id)
        ->assertJsonCount(2, 'data.availableProjects.0.members')
        ->assertJsonPath('data.activeProject', null)
        ->assertJsonPath('data.can.createProject', true)
        ->assertJsonPath('data.can.manageProject', false)
        ->assertJsonCount(4, 'data.taskOptions.statuses')
        ->assertJsonCount(4, 'data.taskOptions.importances');

    expect(collect($response->json('data.projects'))->pluck('id'))->not->toContain($hiddenProject->id)
        ->and($response->json('data'))->toEqual(json_decode(json_encode(app(ProjectPageData::class)->build($owner->fresh())), true));

    $this->getJson(route('mobile.v1.projects.index'))
        ->assertSuccessful()
        ->assertExactJson($response->json());
});

test('tasks can be moved across kanban stages through the mobile api and the move is logged', function () {
    $creator = User::factory()->create(['language' => 'en']);
    $assignee = User::factory()->create(['language' => 'en']);
    $outsider = User::factory()->create(['language' => 'en']);
    $task = mobileTasksChatsContactsStandaloneTask($creator, [
        'assignee_user_id' => $assignee->id,
        'title' => 'Kanban card',
    ]);

    $this->withToken(mobileTasksChatsContactsToken($assignee));

    $this->patchJson(route('mobile.v1.tasks.move', $task), ['status' => ' '.ProjectTask::STATUS_DONE.' '])
        ->assertSuccessful()
        ->assertJsonPath('message', trans('ui.projects.task_updated_success', [], 'en'))
        ->assertJsonPath('data.id', $task->id)
        ->assertJsonPath('data.status', ProjectTask::STATUS_DONE)
        ->assertJsonPath('data.project_id', null)
        ->assertJsonPath('data.project', null)
        ->assertJsonPath('data.parent_task', null)
        ->assertJsonPath('data.creator.id', $creator->id)
        ->assertJsonPath('data.assignee.id', $assignee->id)
        ->assertJsonPath('data.co_assignees', [])
        ->assertJsonPath('data.subtasks', [])
        ->assertJsonPath('data.completed_at', fn (mixed $value): bool => is_string($value) && $value !== '')
        ->assertJsonStructure([
            'message',
            'data' => [
                'id', 'project_id', 'parent_task_id', 'title', 'description', 'status', 'importance', 'complexity', 'due_at',
                'completed_at', 'sort_order', 'project', 'parent_task', 'co_assignees', 'subtasks', 'created_at', 'updated_at',
                'creator' => ['id', 'name', 'last_name', 'middle_name', 'email', 'phone', 'position', 'avatar', 'avatar_scale', 'group'],
                'assignee' => ['id', 'name', 'last_name', 'email', 'avatar'],
            ],
        ]);

    $task->refresh();
    $changeLog = $task->chatConversation()->firstOrFail()->messages()->latest('id')->firstOrFail();

    expect($task->status)->toBe(ProjectTask::STATUS_DONE)
        ->and($task->completed_at)->not->toBeNull()
        ->and($task->updated_by_user_id)->toBe($assignee->id)
        ->and($changeLog->user_id)->toBe($assignee->id)
        ->and($changeLog->body)->toContain(trans('ui.projects.task_change_log_heading', [], 'en'))
        ->and($changeLog->body)->toContain(
            '- '.trans('ui.projects.status', [], 'en').': '
            .trans('ui.projects.status_'.ProjectTask::STATUS_TODO, [], 'en')
            .' -> '
            .trans('ui.projects.status_'.ProjectTask::STATUS_DONE, [], 'en'),
        );

    $this->patchJson(route('mobile.v1.tasks.move', $task), ['status' => ProjectTask::STATUS_IN_PROGRESS])
        ->assertSuccessful()
        ->assertJsonPath('data.status', ProjectTask::STATUS_IN_PROGRESS)
        ->assertJsonPath('data.completed_at', null);

    $this->patchJson(route('mobile.v1.tasks.move', $task), ['status' => 'archived'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
    $this->patchJson(route('mobile.v1.tasks.move', 999999), ['status' => ProjectTask::STATUS_DONE])->assertNotFound();

    $this->withToken(mobileTasksChatsContactsToken($outsider))
        ->patchJson(route('mobile.v1.tasks.move', $task), ['status' => ProjectTask::STATUS_DONE])
        ->assertForbidden();

    expect($task->fresh()->status)->toBe(ProjectTask::STATUS_IN_PROGRESS)
        ->and($task->fresh()->completed_at)->toBeNull();
});

test('kanban stages can be created renamed and reordered through the mobile api', function () {
    $user = User::factory()->create(['language' => 'en']);
    $todoStage = ProjectTaskStage::query()->where('slug', ProjectTaskStage::SLUG_TODO)->firstOrFail();
    $doneStage = ProjectTaskStage::query()->where('slug', ProjectTaskStage::SLUG_DONE)->firstOrFail();

    $this->withToken(mobileTasksChatsContactsToken($user));

    $stageId = $this->postJson(route('mobile.v1.task-stages.store'), [
        'name' => '  Waiting client ',
        'color' => '#f97316',
    ])
        ->assertCreated()
        ->assertJsonPath('message', trans('ui.projects.stage_created_success', [], 'en'))
        ->assertJsonPath('data.value', 'waiting_client')
        ->assertJsonPath('data.label', 'Waiting client')
        ->assertJsonPath('data.color', '#F97316')
        ->assertJsonPath('data.is_completed', false)
        ->assertJsonPath('data.sort_order', 3)
        ->assertJsonStructure(['message', 'data' => ['id', 'value', 'label', 'color', 'is_completed', 'sort_order']])
        ->json('data.id');

    expect($doneStage->fresh()->sort_order)->toBe(4)
        ->and(ProjectTaskStage::query()->find($stageId)?->slug)->toBe('waiting_client');

    $this->postJson(route('mobile.v1.task-stages.store'), ['name' => 'Waiting client', 'color' => '#F97316'])
        ->assertCreated()
        ->assertJsonPath('data.value', 'waiting_client_1')
        ->assertJsonPath('data.sort_order', 4);

    $this->postJson(route('mobile.v1.task-stages.store'), ['name' => '', 'color' => 'orange'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'color']);

    $this->patchJson(route('mobile.v1.task-stages.update', $todoStage), ['name' => 'Inbox', 'color' => '#0f766e'])
        ->assertSuccessful()
        ->assertJsonPath('message', trans('ui.projects.stage_updated_success', [], 'en'))
        ->assertJsonPath('data', [
            'id' => $todoStage->id,
            'value' => ProjectTaskStage::SLUG_TODO,
            'label' => 'Inbox',
            'color' => '#0F766E',
            'is_completed' => false,
            'sort_order' => 0,
        ]);

    $this->patchJson(route('mobile.v1.task-stages.update', 999999), ['name' => 'Missing', 'color' => '#000000'])->assertNotFound();

    $orderedStageIds = ProjectTaskStage::query()->ordered()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    $reorderedStageIds = [$orderedStageIds[1], $orderedStageIds[0], ...array_slice($orderedStageIds, 2)];

    $this->patchJson(route('mobile.v1.task-stages.move'), ['stage_ids' => $reorderedStageIds])
        ->assertSuccessful()
        ->assertJsonPath('message', trans('ui.projects.stage_updated_success', [], 'en'))
        ->assertJsonCount(6, 'data')
        ->assertJsonPath('data.0.id', $reorderedStageIds[0])
        ->assertJsonPath('data.0.sort_order', 0)
        ->assertJsonPath('data.1.id', $todoStage->id)
        ->assertJsonPath('data.1.label', 'Inbox')
        ->assertJsonPath('data.1.sort_order', 1)
        ->assertJsonPath('data.5.value', ProjectTaskStage::SLUG_DONE);

    expect(ProjectTaskStage::query()->ordered()->pluck('id')->all())->toBe($reorderedStageIds);

    $this->patchJson(route('mobile.v1.task-stages.move'), ['stage_ids' => [$orderedStageIds[0]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['stage_ids' => trans('ui.projects.validation_stage_order', [], 'en')]);

    $this->getJson(route('mobile.v1.tasks.index'))
        ->assertSuccessful()
        ->assertJsonCount(6, 'data.taskOptions.statuses')
        ->assertJsonPath('data.taskOptions.statuses.0.id', $reorderedStageIds[0])
        ->assertJsonPath('data.taskOptions.statuses.1.label', 'Inbox');
});

test('the task discussion is opened through the mobile api and participants post into it through the chat endpoint', function () {
    Storage::fake('local');

    $creator = User::factory()->create(['language' => 'en']);
    $assignee = User::factory()->create(['language' => 'en']);
    $outsider = User::factory()->create(['language' => 'en']);
    $task = mobileTasksChatsContactsStandaloneTask($creator, [
        'assignee_user_id' => $assignee->id,
        'title' => 'Mobile discussion',
    ]);

    $this->withToken(mobileTasksChatsContactsToken($creator))->withHeader('Accept', 'application/json');

    $conversationId = $this->getJson(route('mobile.v1.tasks.conversation.show', $task))
        ->assertSuccessful()
        ->assertJsonPath('data.conversation.type', ChatConversation::TYPE_TASK)
        ->assertJsonPath('data.conversation.title', 'Mobile discussion')
        ->assertJsonPath('data.conversation.subtitle', null)
        ->assertJsonPath('data.conversation.messages', [])
        ->assertJsonPath('data.conversation.pinnedMessages', [])
        ->assertJsonCount(2, 'data.conversation.participants')
        ->assertJsonStructure([
            'data' => [
                'conversation' => [
                    'id', 'type', 'title', 'subtitle', 'messages', 'pinnedMessages',
                    'participant' => ['id', 'name', 'email', 'phone', 'avatar', 'avatarScale'],
                    'participants' => [['id', 'name', 'email', 'phone', 'avatar', 'avatarScale']],
                ],
            ],
        ])
        ->json('data.conversation.id');

    expect($conversationId)->toBe($task->chatConversation()->value('id'));

    $this->postJson(route('mobile.v1.chats.messages.store', $conversationId), ['body' => "First update\r\nSecond line"])
        ->assertCreated()
        ->assertJsonPath('message', trans('ui.chat.message_sent', [], 'en'))
        ->assertJsonPath('data.chat_conversation_id', $conversationId)
        ->assertJsonPath('data.body', "First update\nSecond line")
        ->assertJsonPath('data.is_own', true)
        ->assertJsonPath('data.user.id', $creator->id);

    $this->withToken(mobileTasksChatsContactsToken($assignee));

    $this->getJson(route('mobile.v1.tasks.conversation.show', $task))
        ->assertSuccessful()
        ->assertJsonPath('data.conversation.id', $conversationId)
        ->assertJsonCount(1, 'data.conversation.messages')
        ->assertJsonPath('data.conversation.messages.0.body', "First update\nSecond line")
        ->assertJsonPath('data.conversation.messages.0.isOwn', false)
        ->assertJsonPath('data.conversation.messages.0.user.id', $creator->id);

    expect(ChatMessageRead::query()->where('user_id', $assignee->id)->count())->toBe(1);

    $this->post(route('mobile.v1.chats.messages.store', $conversationId), [
        'body' => '',
        'attachments' => [UploadedFile::fake()->create('estimate.pdf', 24, 'application/pdf')],
    ])
        ->assertCreated()
        ->assertJsonPath('data.body', '')
        ->assertJsonPath('data.attachments.0.original_name', 'estimate.pdf');

    $attachment = ChatMessageAttachment::query()->latest('id')->firstOrFail();

    Storage::disk('local')->assertExists($attachment->path);

    $this->withToken(mobileTasksChatsContactsToken($creator));

    $this->getJson(route('mobile.v1.tasks.conversation.show', $task))
        ->assertSuccessful()
        ->assertJsonCount(2, 'data.conversation.messages')
        ->assertJsonPath('data.conversation.messages.0.isOwn', true)
        ->assertJsonPath('data.conversation.messages.0.isRead', true)
        ->assertJsonPath('data.conversation.messages.0.readBy.0.id', $assignee->id)
        ->assertJsonPath('data.conversation.messages.1.user.id', $assignee->id)
        ->assertJsonPath('data.conversation.messages.1.attachments.0.name', 'estimate.pdf')
        ->assertJsonPath('data.conversation.messages.1.attachments.0.downloadUrl', route('mobile.v1.chats.attachments.download', $attachment))
        ->assertJsonPath('data.conversation.messages.1.attachments.0.previewUrl', null);

    $this->get(route('mobile.v1.chats.attachments.download', $attachment))
        ->assertSuccessful()
        ->assertDownload('estimate.pdf');

    $this->withToken(mobileTasksChatsContactsToken($outsider));

    $this->getJson(route('mobile.v1.tasks.conversation.show', $task))->assertNotFound();
    $this->getJson(route('mobile.v1.tasks.conversation.show', 999999))->assertNotFound();
    $this->postJson(route('mobile.v1.chats.messages.store', $conversationId), ['body' => 'Intrusion'])->assertForbidden();
    $this->get(route('mobile.v1.chats.attachments.download', $attachment))->assertForbidden();

    expect(ChatMessage::query()->where('chat_conversation_id', $conversationId)->count())->toBe(2)
        ->and(ChatConversationParticipant::query()->where('chat_conversation_id', $conversationId)->pluck('user_id')->all())
        ->toEqualCanonicalizing([$creator->id, $assignee->id]);
});

test('projects can be created shown updated and deleted through the mobile api', function () {
    $owner = User::factory()->create(['language' => 'en']);
    $member = User::factory()->create(['language' => 'en']);
    $outsider = User::factory()->create(['language' => 'en']);

    $this->withToken(mobileTasksChatsContactsToken($owner));

    $response = $this->postJson(route('mobile.v1.projects.store'), [
        'name' => '  Mobile rollout ',
        'description' => 'Ship the Android client.',
        'is_archived' => false,
        'member_user_ids' => [$member->id],
    ])
        ->assertCreated()
        ->assertJsonPath('message', trans('ui.projects.project_created_success', [], 'en'))
        ->assertJsonPath('data.name', 'Mobile rollout')
        ->assertJsonPath('data.slug', 'mobile-rollout')
        ->assertJsonPath('data.description', 'Ship the Android client.')
        ->assertJsonPath('data.is_archived', false)
        ->assertJsonPath('data.owner.id', $owner->id)
        ->assertJsonCount(2, 'data.members')
        ->assertJsonPath('data.tasks', [])
        ->assertJsonPath('data.members_count', 2)
        ->assertJsonPath('data.tasks_count', 0)
        ->assertJsonPath('data.open_tasks_count', 0)
        ->assertJsonPath('data.completed_tasks_count', 0)
        ->assertJsonStructure([
            'message',
            'data' => [
                'id', 'name', 'slug', 'description', 'is_archived', 'tasks', 'members_count', 'tasks_count',
                'open_tasks_count', 'completed_tasks_count', 'created_at', 'updated_at',
                'owner' => ['id', 'name', 'last_name', 'email', 'avatar', 'is_super_admin', 'group'],
                'members' => [['id', 'name', 'last_name', 'email', 'avatar']],
            ],
        ]);

    $project = Project::query()->findOrFail($response->json('data.id'));

    expect($project->owner_user_id)->toBe($owner->id)
        ->and($project->members()->pluck('users.id')->all())->toEqualCanonicalizing([$owner->id, $member->id]);

    $this->postJson(route('mobile.v1.projects.store'), ['name' => 'Mobile rollout'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slug');

    $this->getJson(route('mobile.v1.projects.show', $project))
        ->assertSuccessful()
        ->assertJsonPath('data.pageMode', ProjectPageData::MODE_PROJECTS)
        ->assertJsonPath('data.activeProject.id', $project->id)
        ->assertJsonPath('data.activeProject.description', 'Ship the Android client.')
        ->assertJsonCount(2, 'data.activeProject.members')
        ->assertJsonCount(1, 'data.taskGroups')
        ->assertJsonPath('data.can.manageProject', true)
        ->assertJsonPath('data.can.workOnActiveProject', true)
        ->assertJsonStructure(['data' => ['activeProject' => ['id', 'name', 'slug', 'description', 'is_archived', 'owner', 'members', 'tasks']]]);

    $this->patchJson(route('mobile.v1.projects.update', $project), [
        'name' => 'Mobile rollout EMEA',
        'slug' => 'mobile-rollout-emea',
        'is_archived' => true,
        'member_user_ids' => [],
    ])
        ->assertSuccessful()
        ->assertJsonPath('message', trans('ui.projects.project_updated_success', [], 'en'))
        ->assertJsonPath('data.id', $project->id)
        ->assertJsonPath('data.name', 'Mobile rollout EMEA')
        ->assertJsonPath('data.slug', 'mobile-rollout-emea')
        ->assertJsonPath('data.description', null)
        ->assertJsonPath('data.is_archived', true)
        ->assertJsonPath('data.members_count', 1);

    $project->members()->sync([$owner->id, $member->id]);

    $this->withToken(mobileTasksChatsContactsToken($member));

    $this->getJson(route('mobile.v1.projects.show', $project))
        ->assertSuccessful()
        ->assertJsonPath('data.can.manageProject', false)
        ->assertJsonPath('data.can.workOnActiveProject', true);
    $this->patchJson(route('mobile.v1.projects.update', $project), ['name' => 'Hijacked'])->assertForbidden();
    $this->deleteJson(route('mobile.v1.projects.destroy', $project))->assertForbidden();

    $this->withToken(mobileTasksChatsContactsToken($outsider));

    $this->getJson(route('mobile.v1.projects.show', $project))->assertNotFound();
    $this->patchJson(route('mobile.v1.projects.update', $project), ['name' => 'Hijacked'])->assertForbidden();
    $this->deleteJson(route('mobile.v1.projects.destroy', $project))->assertNotFound();

    expect($project->fresh()->name)->toBe('Mobile rollout EMEA');

    $this->withToken(mobileTasksChatsContactsToken($owner));

    $this->deleteJson(route('mobile.v1.projects.destroy', $project))
        ->assertSuccessful()
        ->assertExactJson([
            'message' => trans('ui.projects.project_deleted_success', [], 'en'),
            'data' => ['id' => $project->id],
        ]);

    expect(Project::query()->whereKey($project->id)->exists())->toBeFalse();
});

test('tasks can be created shown updated and deleted through the mobile api with notifications and change logs', function () {
    $owner = User::factory()->create(['language' => 'en']);
    $assignee = User::factory()->create(['language' => 'en']);
    $coAssignee = User::factory()->create(['language' => 'en']);
    $newAssignee = User::factory()->create(['language' => 'en']);
    $outsider = User::factory()->create(['language' => 'en']);
    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
    ]);
    $project->members()->sync([$owner->id, $assignee->id, $coAssignee->id, $newAssignee->id]);
    $dueAt = now()->addDays(3)->setTime(16, 45);

    Notification::fake();

    $this->withToken(mobileTasksChatsContactsToken($owner));

    $response = $this->postJson(route('mobile.v1.tasks.store'), [
        'project_id' => $project->id,
        'parent_task_id' => null,
        'title' => '  Prepare release notes ',
        'description' => 'Summarize the changes.',
        'status' => ProjectTask::STATUS_TODO,
        'importance' => ProjectTask::IMPORTANCE_HIGH,
        'complexity' => 6,
        'due_at' => $dueAt->toISOString(),
        'sort_order' => 2,
        'assignee_user_id' => $assignee->id,
        'co_assignee_user_ids' => [$coAssignee->id],
    ])
        ->assertCreated()
        ->assertJsonPath('message', trans('ui.projects.task_created_success', [], 'en'))
        ->assertJsonPath('data.title', 'Prepare release notes')
        ->assertJsonPath('data.project_id', $project->id)
        ->assertJsonPath('data.project', ['id' => $project->id, 'name' => $project->name, 'slug' => $project->slug])
        ->assertJsonPath('data.status', ProjectTask::STATUS_TODO)
        ->assertJsonPath('data.importance', ProjectTask::IMPORTANCE_HIGH)
        ->assertJsonPath('data.complexity', 6)
        ->assertJsonPath('data.sort_order', 2)
        ->assertJsonPath('data.due_at', $dueAt->toISOString())
        ->assertJsonPath('data.completed_at', null)
        ->assertJsonPath('data.creator.id', $owner->id)
        ->assertJsonPath('data.assignee.id', $assignee->id)
        ->assertJsonPath('data.co_assignees.0.id', $coAssignee->id);

    $task = ProjectTask::query()->findOrFail($response->json('data.id'));
    $conversation = $task->chatConversation()->firstOrFail();

    Notification::assertSentTo($assignee, SystemNotification::class);
    Notification::assertSentTo($coAssignee, SystemNotification::class);
    Notification::assertSentTimes(SystemNotification::class, 2);

    expect($conversation->participants()->pluck('user_id')->all())
        ->toEqualCanonicalizing([$owner->id, $assignee->id, $coAssignee->id]);

    $this->postJson(route('mobile.v1.tasks.store'), [
        'title' => 'Personal reminder',
        'status' => ProjectTask::STATUS_IN_PROGRESS,
        'importance' => ProjectTask::IMPORTANCE_LOW,
    ])
        ->assertCreated()
        ->assertJsonPath('data.project', null)
        ->assertJsonPath('data.assignee', null)
        ->assertJsonPath('data.complexity', 5)
        ->assertJsonPath('data.sort_order', 0);

    $this->postJson(route('mobile.v1.tasks.store'), [
        'project_id' => $project->id,
        'title' => 'Wrong assignee',
        'status' => ProjectTask::STATUS_TODO,
        'importance' => ProjectTask::IMPORTANCE_NORMAL,
        'assignee_user_id' => $outsider->id,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['assignee_user_id' => trans('ui.projects.validation_assignee_member', [], 'en')]);

    $this->getJson(route('mobile.v1.tasks.show', $task))
        ->assertSuccessful()
        ->assertJsonPath('data.pageMode', ProjectPageData::MODE_PROJECTS)
        ->assertJsonPath('data.activeProject.id', $project->id)
        ->assertJsonPath('data.activeTask.id', $task->id)
        ->assertJsonPath('data.activeTask.description', 'Summarize the changes.')
        ->assertJsonPath('data.activeTask.co_assignees.0.id', $coAssignee->id)
        ->assertJsonPath('data.can.manageTask', true)
        ->assertJsonStructure([
            'data' => [
                'activeTask' => [
                    'id', 'project_id', 'project_name', 'parent_task_id', 'parent_task', 'title', 'description', 'status',
                    'importance', 'complexity', 'due_at', 'completed_at', 'created_at', 'sort_order', 'creator', 'assignee',
                    'co_assignees', 'subtasks', 'updated_at',
                ],
            ],
        ]);

    Notification::fake();

    $this->patchJson(route('mobile.v1.tasks.update', $task), [
        'project_id' => $project->id,
        'title' => 'Prepare release notes v2',
        'description' => 'Summarize the changes.',
        'status' => ProjectTask::STATUS_DONE,
        'importance' => ProjectTask::IMPORTANCE_HIGH,
        'complexity' => 6,
        'due_at' => $dueAt->toISOString(),
        'sort_order' => 2,
        'assignee_user_id' => $newAssignee->id,
        'co_assignee_user_ids' => [$coAssignee->id],
    ])
        ->assertSuccessful()
        ->assertJsonPath('message', trans('ui.projects.task_updated_success', [], 'en'))
        ->assertJsonPath('data.title', 'Prepare release notes v2')
        ->assertJsonPath('data.status', ProjectTask::STATUS_DONE)
        ->assertJsonPath('data.assignee.id', $newAssignee->id)
        ->assertJsonPath('data.completed_at', fn (mixed $value): bool => is_string($value) && $value !== '');

    Notification::assertSentTo($newAssignee, SystemNotification::class);
    Notification::assertNotSentTo([$owner, $assignee, $coAssignee], SystemNotification::class);
    Notification::assertSentTimes(SystemNotification::class, 1);

    $changeLog = $conversation->messages()->latest('id')->firstOrFail();

    expect($changeLog->user_id)->toBe($owner->id)
        ->and($changeLog->body)->toContain(trans('ui.projects.task_change_log_heading', [], 'en'))
        ->and($changeLog->body)->toContain('- '.trans('ui.projects.task_title', [], 'en').': "Prepare release notes" -> "Prepare release notes v2"')
        ->and($changeLog->body)->toContain(
            '- '.trans('ui.projects.assignee', [], 'en').': '
            .trim($assignee->name.' '.($assignee->last_name ?? ''))
            .' -> '
            .trim($newAssignee->name.' '.($newAssignee->last_name ?? '')),
        )
        ->and($conversation->participants()->pluck('user_id')->all())
        ->toEqualCanonicalizing([$owner->id, $newAssignee->id, $coAssignee->id]);

    $this->withToken(mobileTasksChatsContactsToken($outsider));

    $this->getJson(route('mobile.v1.tasks.show', $task))->assertNotFound();
    $this->patchJson(route('mobile.v1.tasks.update', $task), ['title' => 'Hijacked'])->assertForbidden();
    $this->deleteJson(route('mobile.v1.tasks.destroy', $task))->assertNotFound();
    $this->postJson(route('mobile.v1.tasks.store'), [
        'project_id' => $project->id,
        'title' => 'Intrusion',
        'status' => ProjectTask::STATUS_TODO,
        'importance' => ProjectTask::IMPORTANCE_NORMAL,
    ])->assertForbidden();

    expect($task->fresh()->title)->toBe('Prepare release notes v2')
        ->and(ProjectTask::query()->where('title', 'Intrusion')->exists())->toBeFalse();

    $this->withToken(mobileTasksChatsContactsToken($owner));

    $this->deleteJson(route('mobile.v1.tasks.destroy', $task))
        ->assertSuccessful()
        ->assertExactJson([
            'message' => trans('ui.projects.task_deleted_success', [], 'en'),
            'data' => ['id' => $task->id],
        ]);

    expect(ProjectTask::query()->whereKey($task->id)->exists())->toBeFalse();
});

test('the mobile chats index returns the sidebar payload and marks the opened conversation as read', function () {
    $user = User::factory()->create(['language' => 'en', 'name' => 'Mobile', 'last_name' => 'Reader']);
    $recipient = User::factory()->create(['language' => 'en', 'name' => 'Zarina', 'last_name' => 'Sender']);
    $outsider = User::factory()->create(['language' => 'en']);
    $conversation = mobileTasksChatsContactsDirectConversation($user, $recipient);
    $firstMessage = ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
        'body' => 'Hello from the web',
    ]);
    $secondMessage = ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
        'body' => 'Are you there?',
    ]);

    $this->withToken(mobileTasksChatsContactsToken($user));

    $this->getJson(route('mobile.v1.chats.index'))
        ->assertSuccessful()
        ->assertJsonPath('unreadCount', 2)
        ->assertJsonPath('activeConversation', null)
        ->assertJsonPath('conversations.0.type', ChatConversation::TYPE_GENERAL)
        ->assertJsonPath('conversations.1.id', $conversation->id)
        ->assertJsonPath('conversations.1.type', ChatConversation::TYPE_DIRECT)
        ->assertJsonPath('conversations.1.title', 'Zarina Sender')
        ->assertJsonPath('conversations.1.subtitle', $recipient->email)
        ->assertJsonPath('conversations.1.excerpt', 'Are you there?')
        ->assertJsonPath('conversations.1.unreadCount', 2)
        ->assertJsonPath('conversations.1.participant.id', $recipient->id)
        ->assertJsonStructure([
            'unreadCount',
            'conversations' => [['id', 'type', 'title', 'subtitle', 'excerpt', 'lastMessageAt', 'unreadCount', 'participant']],
            'contacts' => [['id', 'name', 'email', 'phone', 'avatar', 'avatarScale']],
            'activeConversation',
        ]);

    $this->getJson(route('mobile.v1.chats.index', ['search' => 'Zarina']))
        ->assertSuccessful()
        ->assertJsonCount(2, 'conversations')
        ->assertJsonPath('conversations.1.id', $conversation->id)
        ->assertJsonCount(1, 'contacts')
        ->assertJsonPath('contacts.0.id', $recipient->id)
        ->assertJsonPath('contacts.0.name', 'Zarina Sender');

    $this->getJson(route('mobile.v1.chats.index', ['conversation' => $conversation->id]))
        ->assertSuccessful()
        ->assertJsonPath('unreadCount', 0)
        ->assertJsonPath('conversations.1.unreadCount', 0)
        ->assertJsonPath('activeConversation.id', $conversation->id)
        ->assertJsonPath('activeConversation.type', ChatConversation::TYPE_DIRECT)
        ->assertJsonPath('activeConversation.title', 'Zarina Sender')
        ->assertJsonPath('activeConversation.participant.id', $recipient->id)
        ->assertJsonCount(2, 'activeConversation.participants')
        ->assertJsonCount(2, 'activeConversation.messages')
        ->assertJsonPath('activeConversation.messages.0.id', $firstMessage->id)
        ->assertJsonPath('activeConversation.messages.1.id', $secondMessage->id)
        ->assertJsonPath('activeConversation.messages.1.body', 'Are you there?')
        ->assertJsonPath('activeConversation.messages.1.isOwn', false)
        ->assertJsonPath('activeConversation.pinnedMessages', [])
        ->assertJsonStructure([
            'activeConversation' => [
                'messages' => [[
                    'id', 'body', 'createdAt', 'editedAt', 'deletedAt', 'pinnedAt', 'isEdited', 'isDeleted', 'isPinned',
                    'isOwn', 'isRead', 'readCount', 'readBy', 'attachments',
                    'user' => ['id', 'name', 'email', 'phone', 'avatar', 'avatarScale'],
                ]],
            ],
        ]);

    $participant = ChatConversationParticipant::query()
        ->where('chat_conversation_id', $conversation->id)
        ->where('user_id', $user->id)
        ->firstOrFail();

    expect($participant->last_read_at)->not->toBeNull()
        ->and(ChatMessageRead::query()->where('user_id', $user->id)->pluck('chat_message_id')->all())
        ->toEqualCanonicalizing([$firstMessage->id, $secondMessage->id]);

    $this->withToken(mobileTasksChatsContactsToken($recipient));

    $this->getJson(route('mobile.v1.chats.index', ['conversation' => $conversation->id]))
        ->assertSuccessful()
        ->assertJsonPath('activeConversation.title', 'Mobile Reader')
        ->assertJsonPath('activeConversation.messages.1.isOwn', true)
        ->assertJsonPath('activeConversation.messages.1.isRead', true)
        ->assertJsonPath('activeConversation.messages.1.readCount', 1)
        ->assertJsonPath('activeConversation.messages.1.readBy', [['id' => $user->id, 'name' => 'Mobile Reader']]);

    $this->withToken(mobileTasksChatsContactsToken($outsider));

    $this->getJson(route('mobile.v1.chats.index', ['conversation' => $conversation->id]))->assertNotFound();
    $this->getJson(route('mobile.v1.chats.index', ['conversation' => 'latest']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('conversation');
});

test('mobile users can start direct chats and exchange messages with multipart attachments', function () {
    Storage::fake('local');

    $user = User::factory()->create(['language' => 'en']);
    $recipient = User::factory()->create(['language' => 'en']);
    $inactiveUser = User::factory()->create(['is_active' => false]);

    $this->withToken(mobileTasksChatsContactsToken($user))->withHeader('Accept', 'application/json');

    $conversationId = $this->postJson(route('mobile.v1.chats.direct.store'), ['user_id' => $recipient->id])
        ->assertCreated()
        ->assertJsonPath('message', trans('ui.chat.direct_chat_ready', [], 'en'))
        ->assertJsonStructure(['message', 'data' => ['conversation_id']])
        ->json('data.conversation_id');

    $this->postJson(route('mobile.v1.chats.direct.store'), ['user_id' => $recipient->id])
        ->assertCreated()
        ->assertJsonPath('data.conversation_id', $conversationId);
    $this->postJson(route('mobile.v1.chats.direct.store'), ['user_id' => $user->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id' => trans('ui.chat.cannot_message_yourself', [], 'en')]);
    $this->postJson(route('mobile.v1.chats.direct.store'), ['user_id' => $inactiveUser->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id' => trans('ui.chat.user_unavailable', [], 'en')]);

    $response = $this->post(route('mobile.v1.chats.messages.store', $conversationId), [
        'body' => "Contract draft\r\nsee the attachments",
        'attachments' => [
            UploadedFile::fake()->image('scan.png'),
            UploadedFile::fake()->create('contract.pdf', 64, 'application/pdf'),
            UploadedFile::fake()->create('voice.mp3', 32, 'audio/mpeg'),
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('message', trans('ui.chat.message_sent', [], 'en'))
        ->assertJsonPath('data.chat_conversation_id', $conversationId)
        ->assertJsonPath('data.body', "Contract draft\nsee the attachments")
        ->assertJsonPath('data.is_own', true)
        ->assertJsonPath('data.is_edited', false)
        ->assertJsonPath('data.is_deleted', false)
        ->assertJsonPath('data.edited_at', null)
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonCount(3, 'data.attachments')
        ->assertJsonPath('data.attachments.0.original_name', 'scan.png')
        ->assertJsonPath('data.attachments.0.mime_type', 'image/png')
        ->assertJsonPath('data.attachments.0.extension', 'png')
        ->assertJsonPath('data.attachments.1.mime_type', 'application/pdf')
        ->assertJsonPath('data.attachments.1.preview_url', null)
        ->assertJsonPath('data.attachments.1.audio_url', null)
        ->assertJsonPath('data.attachments.2.preview_url', null)
        ->assertJsonStructure([
            'message',
            'data' => [
                'id', 'chat_conversation_id', 'body', 'created_at', 'edited_at', 'deleted_at', 'updated_at', 'is_edited',
                'is_deleted', 'is_own',
                'user' => ['id', 'name', 'last_name', 'email', 'avatar'],
                'attachments' => [['id', 'original_name', 'mime_type', 'extension', 'size_bytes', 'download_url', 'preview_url', 'audio_url']],
            ],
        ]);

    $attachments = ChatMessageAttachment::query()->orderBy('id')->get();

    foreach ($attachments as $attachment) {
        Storage::disk('local')->assertExists($attachment->path);
    }

    expect($attachments)->toHaveCount(3)
        ->and($response->json('data.attachments.0.download_url'))->toBe(route('mobile.v1.chats.attachments.download', $attachments[0]))
        ->and($response->json('data.attachments.0.preview_url'))->toBe(route('mobile.v1.chats.attachments.preview', $attachments[0]))
        ->and($response->json('data.attachments.2.audio_url'))->toBe(route('mobile.v1.chats.attachments.preview', $attachments[2]))
        ->and($response->json('data.attachments.1.size_bytes'))->toBe(64 * 1024);

    $messageId = $response->json('data.id');

    $this->postJson(route('mobile.v1.chats.messages.store', $conversationId), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body' => trans('ui.chat.message_or_attachment_required', [], 'en')]);
    $this->postJson(route('mobile.v1.chats.messages.store', $conversationId), ['body' => '   '])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');
    $this->post(route('mobile.v1.chats.messages.store', $conversationId), [
        'attachments' => collect(range(1, 11))
            ->map(fn (int $index): UploadedFile => UploadedFile::fake()->create('note-'.$index.'.txt', 1, 'text/plain'))
            ->all(),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attachments');
    $this->post(route('mobile.v1.chats.messages.store', $conversationId), [
        'attachments' => [UploadedFile::fake()->create('archive.zip', 20481, 'application/zip')],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attachments.0');

    $this->patchJson(route('mobile.v1.chats.messages.update', [$conversationId, $messageId]), ['body' => 'Contract draft v2'])
        ->assertSuccessful()
        ->assertJsonPath('message', trans('ui.chat.message_sent', [], 'en'))
        ->assertJsonPath('data.id', $messageId)
        ->assertJsonPath('data.body', 'Contract draft v2')
        ->assertJsonPath('data.is_edited', true)
        ->assertJsonCount(3, 'data.attachments');

    $this->withToken(mobileTasksChatsContactsToken($recipient));

    $this->getJson(route('mobile.v1.chats.index', ['conversation' => $conversationId]))
        ->assertSuccessful()
        ->assertJsonPath('activeConversation.messages.0.isEdited', true)
        ->assertJsonPath('activeConversation.messages.0.attachments.0.name', 'scan.png')
        ->assertJsonPath('activeConversation.messages.0.attachments.0.downloadUrl', route('mobile.v1.chats.attachments.download', $attachments[0]))
        ->assertJsonPath('activeConversation.messages.0.attachments.0.previewUrl', route('mobile.v1.chats.attachments.preview', $attachments[0]))
        ->assertJsonPath('activeConversation.messages.0.attachments.2.audioUrl', route('mobile.v1.chats.attachments.preview', $attachments[2]));

    $this->patchJson(route('mobile.v1.chats.messages.update', [$conversationId, $messageId]), ['body' => 'Hijacked'])->assertForbidden();
    $this->deleteJson(route('mobile.v1.chats.messages.destroy', [$conversationId, $messageId]))->assertForbidden();

    $this->withToken(mobileTasksChatsContactsToken($user));

    $this->deleteJson(route('mobile.v1.chats.messages.destroy', [$conversationId, $messageId]))
        ->assertSuccessful()
        ->assertJsonPath('data.id', $messageId)
        ->assertJsonPath('data.is_deleted', true)
        ->assertJsonPath('data.body', trans('ui.chat.deleted_message', [], 'en'))
        ->assertJsonPath('data.attachments', []);

    $this->get(route('mobile.v1.chats.attachments.download', $attachments[1]))->assertNotFound();

    expect(ChatMessage::query()->findOrFail($messageId)->body)->toBe('Contract draft v2');
});

test('chat messages can be pinned and unpinned through the mobile api', function () {
    $user = User::factory()->create(['language' => 'en']);
    $recipient = User::factory()->create(['language' => 'en']);
    $outsider = User::factory()->create(['language' => 'en']);
    $conversation = mobileTasksChatsContactsDirectConversation($user, $recipient);
    $message = ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
        'body' => 'Important update',
    ]);
    $deletedMessage = ChatMessage::query()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $recipient->id,
        'body' => 'Removed update',
        'deleted_at' => now(),
    ]);
    $foreignConversation = mobileTasksChatsContactsDirectConversation($recipient, $outsider);
    $foreignMessage = ChatMessage::query()->create([
        'chat_conversation_id' => $foreignConversation->id,
        'user_id' => $outsider->id,
        'body' => 'Foreign update',
    ]);

    $this->withToken(mobileTasksChatsContactsToken($user));

    $this->patchJson(route('mobile.v1.chats.messages.pin', [$conversation, $message]))
        ->assertSuccessful()
        ->assertJsonPath('message', trans('ui.chat.message_pinned', [], 'en'))
        ->assertJsonPath('data.id', $message->id)
        ->assertJsonPath('data.body', 'Important update')
        ->assertJsonPath('data.isPinned', true)
        ->assertJsonPath('data.isOwn', false)
        ->assertJsonPath('data.pinnedAt', fn (mixed $value): bool => is_string($value) && $value !== '')
        ->assertJsonPath('data.user.id', $recipient->id)
        ->assertJsonStructure([
            'message',
            'data' => [
                'id', 'body', 'createdAt', 'editedAt', 'deletedAt', 'pinnedAt', 'isEdited', 'isDeleted', 'isPinned', 'isOwn',
                'isRead', 'readCount', 'readBy', 'user', 'attachments',
            ],
        ]);

    expect($message->fresh()->isPinned())->toBeTrue()
        ->and($message->fresh()->pinned_by_user_id)->toBe($user->id);

    $this->getJson(route('mobile.v1.chats.index', ['conversation' => $conversation->id]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'activeConversation.pinnedMessages')
        ->assertJsonPath('activeConversation.pinnedMessages.0.id', $message->id);

    $this->deleteJson(route('mobile.v1.chats.messages.unpin', [$conversation, $message]))
        ->assertSuccessful()
        ->assertJsonPath('message', trans('ui.chat.message_unpinned', [], 'en'))
        ->assertJsonPath('data.id', $message->id)
        ->assertJsonPath('data.isPinned', false)
        ->assertJsonPath('data.pinnedAt', null);

    expect($message->fresh()->isPinned())->toBeFalse()
        ->and($message->fresh()->pinned_by_user_id)->toBeNull();

    $this->patchJson(route('mobile.v1.chats.messages.pin', [$conversation, $foreignMessage]))->assertNotFound();
    $this->patchJson(route('mobile.v1.chats.messages.pin', [$conversation, $deletedMessage]))->assertNotFound();
    $this->patchJson(route('mobile.v1.chats.messages.pin', [$conversation, 999999]))->assertNotFound();
    $this->deleteJson(route('mobile.v1.chats.messages.unpin', [$conversation, $deletedMessage]))->assertNotFound();

    $this->withToken(mobileTasksChatsContactsToken($outsider));

    $this->patchJson(route('mobile.v1.chats.messages.pin', [$conversation, $message]))->assertForbidden();
    $this->deleteJson(route('mobile.v1.chats.messages.unpin', [$conversation, $message]))->assertForbidden();

    expect($message->fresh()->isPinned())->toBeFalse()
        ->and($foreignMessage->fresh()->isPinned())->toBeFalse()
        ->and($deletedMessage->fresh()->isPinned())->toBeFalse();
});

test('messages of a task discussion can be pinned by users who can view the task', function () {
    $creator = User::factory()->create(['language' => 'en']);
    $assignee = User::factory()->create(['language' => 'en']);
    $outsider = User::factory()->create(['language' => 'en']);
    $task = mobileTasksChatsContactsStandaloneTask($creator, ['assignee_user_id' => $assignee->id]);

    $this->withToken(mobileTasksChatsContactsToken($creator));

    $conversationId = $this->getJson(route('mobile.v1.tasks.conversation.show', $task))
        ->assertSuccessful()
        ->json('data.conversation.id');
    $messageId = $this->postJson(route('mobile.v1.chats.messages.store', $conversationId), ['body' => 'Decision: ship on Friday'])
        ->assertCreated()
        ->json('data.id');

    $this->withToken(mobileTasksChatsContactsToken($assignee));

    $this->patchJson(route('mobile.v1.chats.messages.pin', [$conversationId, $messageId]))
        ->assertSuccessful()
        ->assertJsonPath('data.isPinned', true);

    $this->getJson(route('mobile.v1.tasks.conversation.show', $task))
        ->assertSuccessful()
        ->assertJsonPath('data.conversation.pinnedMessages.0.id', $messageId);

    $this->withToken(mobileTasksChatsContactsToken($outsider));

    $this->deleteJson(route('mobile.v1.chats.messages.unpin', [$conversationId, $messageId]))->assertForbidden();

    expect(ChatMessage::query()->findOrFail($messageId)->isPinned())->toBeTrue();
});

test('chat attachments can be previewed and downloaded through the mobile api by participants only', function () {
    Storage::fake('local');

    $user = User::factory()->create(['language' => 'en']);
    $recipient = User::factory()->create(['language' => 'en']);
    $outsider = User::factory()->create(['language' => 'en']);
    $conversation = mobileTasksChatsContactsDirectConversation($user, $recipient);

    $this->withToken(mobileTasksChatsContactsToken($user))->withHeader('Accept', 'application/json');

    $messageId = $this->post(route('mobile.v1.chats.messages.store', $conversation->id), [
        'body' => 'Files for review',
        'attachments' => [
            UploadedFile::fake()->image('photo.png'),
            UploadedFile::fake()->create('notes.txt', 4, 'text/plain'),
            UploadedFile::fake()->create('voice.ogg', 16, 'audio/ogg'),
        ],
    ])
        ->assertCreated()
        ->json('data.id');

    [$image, $text, $audio] = ChatMessageAttachment::query()->orderBy('id')->get()->all();

    $this->withToken(mobileTasksChatsContactsToken($recipient));

    $imagePreview = $this->get(route('mobile.v1.chats.attachments.preview', $image))
        ->assertSuccessful()
        ->assertHeader('x-content-type-options', 'nosniff');

    expect($imagePreview->headers->get('content-type'))->toContain('image/png')
        ->and($imagePreview->headers->get('content-disposition'))->toStartWith('inline;')
        ->and($imagePreview->headers->get('content-disposition'))->toContain('photo.png');

    $audioPreview = $this->get(route('mobile.v1.chats.attachments.preview', $audio))
        ->assertSuccessful();

    expect($audioPreview->headers->get('content-type'))->toContain('audio/ogg');

    $this->get(route('mobile.v1.chats.attachments.preview', $text))->assertNotFound();

    $this->get(route('mobile.v1.chats.attachments.download', $text))
        ->assertSuccessful()
        ->assertDownload('notes.txt');

    $this->get(route('mobile.v1.chats.attachments.download', $image))
        ->assertSuccessful()
        ->assertDownload('photo.png');

    $this->get(route('mobile.v1.chats.attachments.download', 999999))->assertNotFound();
    $this->get(route('mobile.v1.chats.attachments.preview', 999999))->assertNotFound();

    $this->withToken(mobileTasksChatsContactsToken($outsider));

    $this->get(route('mobile.v1.chats.attachments.preview', $image))->assertForbidden();
    $this->get(route('mobile.v1.chats.attachments.download', $text))->assertForbidden();

    $this->withToken(mobileTasksChatsContactsToken($user));

    $this->deleteJson(route('mobile.v1.chats.messages.destroy', [$conversation->id, $messageId]))->assertSuccessful();

    $this->get(route('mobile.v1.chats.attachments.preview', $image))->assertNotFound();
    $this->get(route('mobile.v1.chats.attachments.download', $text))->assertNotFound();
});

test('the mobile chat profile endpoint returns the web chat profile payload', function () {
    $viewer = User::factory()->create(['language' => 'en']);
    $colleague = User::factory()->create([
        'name' => 'Jane',
        'last_name' => 'Doe',
        'phone' => '+7 700 000 0000',
        'position' => 'Engineer',
    ]);

    $this->withToken(mobileTasksChatsContactsToken($viewer));

    $response = $this->getJson(route('mobile.v1.chats.users.show', $colleague))
        ->assertSuccessful()
        ->assertJsonPath('data.id', $colleague->id)
        ->assertJsonPath('data.name', 'Jane')
        ->assertJsonPath('data.last_name', 'Doe')
        ->assertJsonPath('data.phone', '+7 700 000 0000')
        ->assertJsonPath('data.position', 'Engineer')
        ->assertJsonPath('data.email_verified_at', $colleague->email_verified_at?->toISOString())
        ->assertJsonPath('data.issued_equipment', [])
        ->assertJsonPath('data.subordinates', [])
        ->assertJsonPath('canEdit', false)
        ->assertJsonPath('managerOptions', [])
        ->assertJsonStructure([
            'data' => [
                'id', 'name', 'last_name', 'middle_name', 'email', 'phone', 'position', 'email_verified_at', 'avatar',
                'avatar_scale', 'created_at', 'updated_at', 'is_super_admin', 'is_active', 'deactivated_at', 'issued_equipment',
                'group', 'manager_id', 'manager', 'subordinates_count', 'subordinates',
            ],
            'canEdit',
            'managerOptions',
        ]);

    $this->getJson(route('mobile.v1.chats.users.show', 999999))->assertNotFound();

    $administrators = UserGroup::query()->firstOrCreate(['name' => UserGroup::ADMINISTRATORS_NAME]);
    $administrator = User::factory()->create(['user_group_id' => $administrators->id, 'language' => 'en']);

    $this->withToken(mobileTasksChatsContactsToken($administrator))
        ->getJson(route('mobile.v1.chats.users.show', $colleague))
        ->assertSuccessful()
        ->assertJsonPath('canEdit', true)
        ->assertJsonPath('managerOptions', fn (array $options): bool => collect($options)->contains('id', $colleague->id))
        ->assertJsonStructure(['managerOptions' => [['id', 'name', 'last_name', 'middle_name', 'full_name', 'email', 'position', 'avatar', 'avatar_scale', 'is_active']]]);

    app('auth')->forgetGuards();

    $this->withToken('')
        ->actingAs($viewer, 'web')
        ->getJson(route('chats.users.show', $colleague))
        ->assertSuccessful()
        ->assertExactJson($response->json());
});

test('contact history comments can be listed and added through the mobile api', function () {
    $user = mobileTasksChatsContactsGroupUser([UserGroup::PERMISSION_ACCESS_PERSON_CONTACTS]);
    $author = User::factory()->create([
        'name' => 'Aigerim',
        'last_name' => 'Manager',
        'avatar_path' => 'avatars/aigerim.png',
    ]);
    $person = Contact::factory()->person()->create(['name' => 'History Person']);
    $company = Contact::factory()->company()->create();
    $olderComment = ContactComment::factory()->create([
        'contact_id' => $person->id,
        'created_by_user_id' => $author->id,
        'content' => 'First call: discussed the requirements.',
        'created_at' => now()->subDay(),
    ]);
    $newerComment = ContactComment::factory()->create([
        'contact_id' => $person->id,
        'created_by_user_id' => null,
        'content' => 'Imported note.',
        'created_at' => now()->subHour(),
    ]);
    ContactComment::factory()->create(['contact_id' => $company->id]);

    $this->withToken(mobileTasksChatsContactsToken($user));

    $this->getJson(route('mobile.v1.contacts.comments.index', $person))
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newerComment->id)
        ->assertJsonPath('data.0.created_by', null)
        ->assertJsonPath('data.1', [
            'id' => $olderComment->id,
            'content' => 'First call: discussed the requirements.',
            'created_at' => $olderComment->created_at?->toISOString(),
            'created_by' => [
                'id' => $author->id,
                'name' => 'Aigerim',
                'last_name' => 'Manager',
                'avatar' => Storage::disk('public')->url('avatars/aigerim.png'),
            ],
        ]);

    $commentId = $this->postJson(route('mobile.v1.contacts.comments.store', $person), [
        'content' => '  Meeting agreed for Friday.  ',
    ])
        ->assertCreated()
        ->assertJsonPath('message', trans('ui.contacts.comment_created_success', [], 'en'))
        ->assertJsonPath('data.content', 'Meeting agreed for Friday.')
        ->assertJsonPath('data.created_by.id', $user->id)
        ->assertJsonPath('data.created_by.avatar', $user->avatar)
        ->assertJsonStructure(['message', 'data' => ['id', 'content', 'created_at', 'created_by' => ['id', 'name', 'last_name', 'avatar']]])
        ->json('data.id');

    $this->getJson(route('mobile.v1.contacts.comments.index', $person))
        ->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.id', $commentId);

    $this->postJson(route('mobile.v1.contacts.comments.store', $person), ['content' => '   '])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content' => trans('ui.contacts.comment_required', [], 'en')]);
    $this->postJson(route('mobile.v1.contacts.comments.store', $person), ['content' => str_repeat('a', 5001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('content');

    $this->getJson(route('mobile.v1.contacts.comments.index', $company))->assertNotFound();
    $this->postJson(route('mobile.v1.contacts.comments.store', $company), ['content' => 'Hidden note'])->assertForbidden();
    $this->getJson(route('mobile.v1.contacts.comments.index', 999999))->assertNotFound();
    $this->postJson(route('mobile.v1.contacts.comments.store', 999999), ['content' => 'Missing note'])->assertForbidden();

    expect(ContactComment::query()->where('contact_id', $company->id)->count())->toBe(1)
        ->and(ContactComment::query()->where('contact_id', $person->id)->count())->toBe(3)
        ->and(ContactComment::query()->findOrFail($commentId)->created_by_user_id)->toBe($user->id);
});

test('the mobile contacts endpoints list filter show create update and delete contacts', function () {
    Storage::fake('public');

    $user = mobileTasksChatsContactsGroupUser([
        UserGroup::PERMISSION_ACCESS_PERSON_CONTACTS,
        UserGroup::PERMISSION_ACCESS_COMPANY_CONTACTS,
    ]);
    $alice = Contact::factory()->person()->create(['name' => 'Alice Person', 'email' => 'alice@example.com']);
    $blocked = Contact::factory()->person()->blacklisted()->create(['name' => 'Blocked Person']);
    $company = Contact::factory()->company()->create(['name' => 'Beta Company']);

    $this->withToken(mobileTasksChatsContactsToken($user))->withHeader('Accept', 'application/json');

    $this->getJson(route('mobile.v1.contacts.index'))
        ->assertSuccessful()
        ->assertJsonPath('can', ['create_person' => true, 'create_company' => true])
        ->assertJsonPath('available_types', [
            ['value' => Contact::TYPE_PERSON, 'label' => trans('ui.contacts.type_person', [], 'en')],
            ['value' => Contact::TYPE_COMPANY, 'label' => trans('ui.contacts.type_company', [], 'en')],
        ])
        ->assertJsonPath('filters', ['search' => '', 'type' => 'all', 'blacklist' => 'all', 'per_page' => 50])
        ->assertJsonPath('per_page_options', [50, 100, 500])
        ->assertJsonPath('meta', ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 3, 'from' => 1, 'to' => 3])
        ->assertJsonPath('data.0.id', $company->id)
        ->assertJsonPath('data.1.id', $blocked->id)
        ->assertJsonPath('data.2.id', $alice->id)
        ->assertJsonPath('data.2.comments', [])
        ->assertJsonStructure([
            'can' => ['create_person', 'create_company'],
            'available_types' => [['value', 'label']],
            'filters' => ['search', 'type', 'blacklist', 'per_page'],
            'data' => [[
                'id', 'type', 'type_label', 'name', 'contact_person', 'position', 'email', 'phone', 'notes', 'is_blacklisted',
                'avatar', 'company_requisites', 'comments', 'created_at', 'updated_at', 'created_by', 'updated_by',
            ]],
            'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
            'per_page_options',
        ]);

    $this->getJson(route('mobile.v1.contacts.index', ['type' => Contact::TYPE_PERSON]))
        ->assertSuccessful()
        ->assertJsonPath('filters.type', Contact::TYPE_PERSON)
        ->assertJsonPath('meta.total', 2);
    $this->getJson(route('mobile.v1.contacts.index', ['blacklist' => Contact::BLACKLIST_FILTER_ONLY]))
        ->assertSuccessful()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $blocked->id);
    $this->getJson(route('mobile.v1.contacts.index', ['blacklist' => Contact::BLACKLIST_FILTER_EXCLUDE, 'search' => 'alice@']))
        ->assertSuccessful()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $alice->id);
    $this->getJson(route('mobile.v1.contacts.index', ['per_page' => 100, 'page' => 2]))
        ->assertSuccessful()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 100)
        ->assertJsonPath('meta.from', null);
    $this->getJson(route('mobile.v1.contacts.index', ['per_page' => 25, 'type' => 'partner', 'blacklist' => 'maybe']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page', 'type', 'blacklist']);

    $this->getJson(route('mobile.v1.contacts.show', $alice))
        ->assertSuccessful()
        ->assertJsonPath('data.id', $alice->id)
        ->assertJsonPath('data.email', 'alice@example.com')
        ->assertJsonPath('data.type_label', trans('ui.contacts.type_person', [], 'en'));
    $this->getJson(route('mobile.v1.contacts.show', 999999))->assertNotFound();

    $response = $this->post(route('mobile.v1.contacts.store'), [
        'type' => Contact::TYPE_COMPANY,
        'name' => '  Mobile Company ',
        'contact_person' => 'John Doe',
        'email' => 'office@example.com',
        'phone' => '+7 700 000 0000',
        'is_blacklisted' => '0',
        'company_requisites' => [
            'bin' => '000000000000',
            'legal_address' => 'Almaty, Example street 1',
            'bank_name' => 'Example Bank',
        ],
        'avatar' => UploadedFile::fake()->image('company.png'),
    ])
        ->assertCreated()
        ->assertJsonPath('message', trans('ui.contacts.created_success', [], 'en'))
        ->assertJsonPath('data.type', Contact::TYPE_COMPANY)
        ->assertJsonPath('data.name', 'Mobile Company')
        ->assertJsonPath('data.contact_person', 'John Doe')
        ->assertJsonPath('data.is_blacklisted', false)
        ->assertJsonPath('data.company_requisites.bin', '000000000000')
        ->assertJsonPath('data.company_requisites.iin', null)
        ->assertJsonPath('data.company_requisites.actual_address', null)
        ->assertJsonPath('data.created_by.id', $user->id)
        ->assertJsonPath('data.updated_by.id', $user->id);

    $createdContact = Contact::query()->findOrFail($response->json('data.id'));
    $firstAvatarPath = (string) $createdContact->avatar_path;

    Storage::disk('public')->assertExists($firstAvatarPath);

    expect($response->json('data.avatar'))->toBe(Storage::disk('public')->url($firstAvatarPath))
        ->and($createdContact->created_by_user_id)->toBe($user->id);

    $this->postJson(route('mobile.v1.contacts.store'), [
        'type' => Contact::TYPE_COMPANY,
        'name' => 'Duplicate Company',
        'company_requisites' => ['bin' => '000000000000'],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_requisites.bin' => trans('ui.contacts.bin_unique', [], 'en')]);

    $this->post(route('mobile.v1.contacts.update', $createdContact), [
        '_method' => 'PATCH',
        'type' => Contact::TYPE_COMPANY,
        'name' => 'Mobile Company LLP',
        'is_blacklisted' => '1',
        'company_requisites' => ['bin' => '000000000000'],
        'avatar' => UploadedFile::fake()->image('company-new.png'),
    ])
        ->assertSuccessful()
        ->assertJsonPath('message', trans('ui.contacts.updated_success', [], 'en'))
        ->assertJsonPath('data.id', $createdContact->id)
        ->assertJsonPath('data.name', 'Mobile Company LLP')
        ->assertJsonPath('data.is_blacklisted', true)
        ->assertJsonPath('data.contact_person', null)
        ->assertJsonPath('data.email', null)
        ->assertJsonPath('data.company_requisites.legal_address', null)
        ->assertJsonPath('data.updated_by.id', $user->id);

    $createdContact->refresh();

    Storage::disk('public')->assertMissing($firstAvatarPath);
    Storage::disk('public')->assertExists((string) $createdContact->avatar_path);

    $this->patchJson(route('mobile.v1.contacts.update', $createdContact), [
        'type' => Contact::TYPE_COMPANY,
        'name' => 'Mobile Company LLP',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.is_blacklisted', true)
        ->assertJsonPath('data.avatar', Storage::disk('public')->url((string) $createdContact->avatar_path));

    $this->deleteJson(route('mobile.v1.contacts.destroy', $createdContact))
        ->assertSuccessful()
        ->assertExactJson([
            'message' => trans('ui.contacts.deleted_success', [], 'en'),
            'data' => ['id' => $createdContact->id],
        ]);

    Storage::disk('public')->assertMissing((string) $createdContact->avatar_path);

    expect(Contact::query()->whereKey($createdContact->id)->exists())->toBeFalse();
});

test('mobile contacts endpoints enforce the contact type permissions', function () {
    $user = mobileTasksChatsContactsGroupUser([UserGroup::PERMISSION_ACCESS_PERSON_CONTACTS]);
    $person = Contact::factory()->person()->create(['name' => 'Visible Person']);
    $company = Contact::factory()->company()->create(['name' => 'Hidden Company']);

    $this->withToken(mobileTasksChatsContactsToken($user));

    $this->getJson(route('mobile.v1.contacts.index'))
        ->assertSuccessful()
        ->assertJsonPath('can', ['create_person' => true, 'create_company' => false])
        ->assertJsonCount(1, 'available_types')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $person->id);
    $this->getJson(route('mobile.v1.contacts.index', ['type' => Contact::TYPE_COMPANY]))
        ->assertSuccessful()
        ->assertJsonPath('filters.type', Contact::TYPE_PERSON)
        ->assertJsonPath('data.0.id', $person->id);
    $this->getJson(route('mobile.v1.contacts.show', $company))->assertNotFound();
    $this->postJson(route('mobile.v1.contacts.store'), ['type' => Contact::TYPE_COMPANY, 'name' => 'Forbidden Company'])->assertForbidden();
    $this->patchJson(route('mobile.v1.contacts.update', $company), ['type' => Contact::TYPE_COMPANY, 'name' => 'Renamed'])->assertForbidden();
    $this->patchJson(route('mobile.v1.contacts.update', $person), ['type' => Contact::TYPE_COMPANY, 'name' => 'Converted'])->assertForbidden();
    $this->deleteJson(route('mobile.v1.contacts.destroy', $company))->assertNotFound();

    expect($company->fresh()->name)->toBe('Hidden Company')
        ->and($person->fresh()->type)->toBe(Contact::TYPE_PERSON)
        ->and(Contact::query()->where('name', 'Forbidden Company')->exists())->toBeFalse();
});
