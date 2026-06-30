<?php

use App\Models\ChatMessage;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('project members can open their project while outsiders get 404', function () {
    $member = User::factory()->create();
    $outsider = User::factory()->create();

    $project = Project::factory()->create([
        'owner_user_id' => $member->id,
        'created_by_user_id' => $member->id,
        'updated_by_user_id' => $member->id,
    ]);
    $project->members()->sync([$member->id]);

    $task = ProjectTask::factory()->create([
        'project_id' => $project->id,
        'creator_user_id' => $member->id,
        'assignee_user_id' => $member->id,
        'updated_by_user_id' => $member->id,
        'status' => ProjectTask::STATUS_TODO,
        'importance' => ProjectTask::IMPORTANCE_HIGH,
    ]);

    $this->actingAs($member)
        ->get(route('projects.show', $project))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/Index')
            ->where('activeProject.id', $project->id)
            ->where('activeTask', null)
        );

    $this->actingAs($outsider)
        ->get(route('projects.show', $project))
        ->assertNotFound();
});

test('super admin can open any isolated project', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $owner = User::factory()->create();
    $superAdmin = User::factory()->create(['email' => 'super@example.com']);

    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
    ]);
    $project->members()->sync([$owner->id]);

    $this->actingAs($superAdmin)
        ->get(route('projects.show', $project))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/Index')
            ->where('activeProject.id', $project->id)
        );
});

test('verified user can create isolated project and is added as member automatically', function () {
    $user = User::factory()->create();
    $teammate = User::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.store'), [
            'name' => 'Client Launch',
            'slug' => 'client-launch',
            'description' => 'Project for a private launch',
            'is_archived' => false,
            'member_user_ids' => [$teammate->id],
        ])
        ->assertRedirect();

    $project = Project::query()->where('slug', 'client-launch')->firstOrFail();

    expect($project->owner_user_id)->toBe($user->id)
        ->and($project->members()->pluck('users.id')->all())
        ->toEqualCanonicalizing([$user->id, $teammate->id]);
});

test('project member can create task with assignee and co assignees from project members', function () {
    $owner = User::factory()->create();
    $assignee = User::factory()->create();
    $coAssignee = User::factory()->create();
    $dueAt = now()->addDays(5)->setTime(14, 30);

    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
    ]);
    $project->members()->sync([$owner->id, $assignee->id, $coAssignee->id]);

    $this->actingAs($owner)
        ->post(route('projects.tasks.store', $project), [
            'title' => 'Prepare timeline',
            'description' => 'Break down the work by sprint.',
            'status' => ProjectTask::STATUS_IN_PROGRESS,
            'importance' => ProjectTask::IMPORTANCE_CRITICAL,
            'complexity' => 8,
            'due_at' => $dueAt->toISOString(),
            'sort_order' => 3,
            'assignee_user_id' => $assignee->id,
            'co_assignee_user_ids' => [$coAssignee->id],
        ])
        ->assertRedirect();

    $task = ProjectTask::query()->where('project_id', $project->id)->firstOrFail();

    expect($task->title)->toBe('Prepare timeline')
        ->and($task->assignee_user_id)->toBe($assignee->id)
        ->and($task->complexity)->toBe(8)
        ->and($task->due_at?->toISOString())->toBe($dueAt->toISOString())
        ->and($task->coAssignees()->pluck('users.id')->all())->toBe([$coAssignee->id]);
});

test('user can create a standalone task directly in the workspace', function () {
    $creator = User::factory()->create();
    $assignee = User::factory()->create();

    $response = $this->actingAs($creator)
        ->post(route('projects.workspace.tasks.store'), [
            'project_id' => null,
            'parent_task_id' => null,
            'title' => 'Inbox follow-up',
            'description' => 'Call the client back.',
            'status' => ProjectTask::STATUS_TODO,
            'importance' => ProjectTask::IMPORTANCE_NORMAL,
            'complexity' => 4,
            'due_at' => now()->addDays(2)->toDateString(),
            'sort_order' => 1,
            'assignee_user_id' => $assignee->id,
            'co_assignee_user_ids' => [],
        ]);

    $task = ProjectTask::query()->where('title', 'Inbox follow-up')->firstOrFail();

    $response->assertRedirect(route('projects.workspace.tasks.show', $task));

    expect($task->project_id)->toBeNull()
        ->and($task->parent_task_id)->toBeNull()
        ->and($task->assignee_user_id)->toBe($assignee->id);

    $this->actingAs($creator)
        ->get(route('projects.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('taskGroups.0.kind', 'standalone')
            ->where('taskGroups.0.tasks.0.title', 'Inbox follow-up')
        );
});

test('user can create a subtask inside the same project group', function () {
    $owner = User::factory()->create();

    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
    ]);
    $project->members()->sync([$owner->id]);

    $parentTask = ProjectTask::factory()->create([
        'project_id' => $project->id,
        'creator_user_id' => $owner->id,
        'assignee_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
        'title' => 'Launch checklist',
    ]);

    $this->actingAs($owner)
        ->post(route('projects.workspace.tasks.store'), [
            'project_id' => $project->id,
            'parent_task_id' => $parentTask->id,
            'title' => 'Prepare legal docs',
            'description' => 'Collect required files.',
            'status' => ProjectTask::STATUS_IN_PROGRESS,
            'importance' => ProjectTask::IMPORTANCE_HIGH,
            'complexity' => 6,
            'due_at' => now()->addDays(3)->toDateString(),
            'sort_order' => 2,
            'assignee_user_id' => $owner->id,
            'co_assignee_user_ids' => [],
        ])
        ->assertRedirect();

    $subtask = ProjectTask::query()->where('title', 'Prepare legal docs')->firstOrFail();

    expect($subtask->project_id)->toBe($project->id)
        ->and($subtask->parent_task_id)->toBe($parentTask->id);

    $this->actingAs($owner)
        ->get(route('projects.show', $project))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('taskGroups.0.tasks.0.title', 'Launch checklist')
            ->where('taskGroups.0.tasks.0.subtasks.0.title', 'Prepare legal docs')
        );
});

test('parent task must stay in the same task group', function () {
    $owner = User::factory()->create();

    $firstProject = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
    ]);
    $secondProject = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
    ]);

    $firstProject->members()->sync([$owner->id]);
    $secondProject->members()->sync([$owner->id]);

    $parentTask = ProjectTask::factory()->create([
        'project_id' => $firstProject->id,
        'creator_user_id' => $owner->id,
        'assignee_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(route('projects.workspace.tasks.store'), [
            'project_id' => $secondProject->id,
            'parent_task_id' => $parentTask->id,
            'title' => 'Cross linked task',
            'description' => 'Should fail.',
            'status' => ProjectTask::STATUS_TODO,
            'importance' => ProjectTask::IMPORTANCE_NORMAL,
            'complexity' => 5,
            'due_at' => now()->addDay()->toDateString(),
            'sort_order' => 0,
            'assignee_user_id' => $owner->id,
            'co_assignee_user_ids' => [],
        ])
        ->assertSessionHasErrors('parent_task_id');
});

test('only involved users can open a standalone task', function () {
    $creator = User::factory()->create();
    $assignee = User::factory()->create();
    $outsider = User::factory()->create();

    $task = ProjectTask::factory()->standalone()->create([
        'creator_user_id' => $creator->id,
        'assignee_user_id' => $assignee->id,
        'updated_by_user_id' => $creator->id,
        'title' => 'Private standalone task',
    ]);

    $this->actingAs($creator)
        ->get(route('projects.workspace.tasks.show', $task))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeTask.id', $task->id)
            ->where('activeTask.project_id', null)
        );

    $this->actingAs($outsider)
        ->get(route('projects.workspace.tasks.show', $task))
        ->assertNotFound();
});

test('task due reminders are sent once to the assignee during the final day', function () {
    $now = now()->startOfMinute();

    $this->travelTo($now);

    $creator = User::factory()->create();
    $assignee = User::factory()->create();

    $dueSoonTask = ProjectTask::factory()->standalone()->create([
        'creator_user_id' => $creator->id,
        'assignee_user_id' => $assignee->id,
        'updated_by_user_id' => $creator->id,
        'title' => 'Prepare final report',
        'status' => ProjectTask::STATUS_IN_PROGRESS,
        'due_at' => $now->copy()->addHours(23),
        'due_reminder_sent_at' => null,
    ]);

    $laterTask = ProjectTask::factory()->standalone()->create([
        'creator_user_id' => $creator->id,
        'assignee_user_id' => $assignee->id,
        'updated_by_user_id' => $creator->id,
        'title' => 'Archive completed files',
        'status' => ProjectTask::STATUS_IN_PROGRESS,
        'due_at' => $now->copy()->addDays(2),
        'due_reminder_sent_at' => null,
    ]);

    $this->artisan('projects:send-due-reminders')
        ->assertSuccessful();

    $notification = $assignee->refresh()->notifications()->latest('created_at')->first();

    expect($notification)->not->toBeNull()
        ->and($notification?->data['title'])->toBe(
            __('ui.notifications.task_due_soon_title', [], $assignee->resolvedLanguage()),
        )
        ->and($notification?->data['message'])->toContain('Prepare final report')
        ->and($notification?->data['action_url'])->toBe(route('projects.workspace.tasks.show', $dueSoonTask))
        ->and($dueSoonTask->fresh()->due_reminder_sent_at)->not->toBeNull()
        ->and($laterTask->fresh()->due_reminder_sent_at)->toBeNull();

    $this->artisan('projects:send-due-reminders')
        ->assertSuccessful();

    expect($assignee->fresh()->notifications()->count())->toBe(1);

    $this->travelBack();
});

test('projects page collects deadline time and transforms it before submit', function () {
    $projectsPage = file_get_contents(resource_path('js/pages/projects/Index.vue'));
    $taskConversationPanel = file_get_contents(resource_path('js/components/ProjectTaskConversationPanel.vue'));
    $consoleRoutes = file_get_contents(base_path('routes/console.php'));

    expect($projectsPage)
        ->toContain('type="datetime-local"')
        ->toContain('formatDateTime')
        ->toContain('normalizeDueAtForSubmission')
        ->toContain('.transform((data) => ({')
        ->toContain('SheetContent side="right"')
        ->toContain('sm:w-[80vw]')
        ->toContain('ProjectTaskConversationPanel')
        ->and($taskConversationPanel)->toContain('task_discussion_placeholder')
        ->and($taskConversationPanel)->toContain('showTaskConversation.url')
        ->and($taskConversationPanel)->toContain('storeMessage.url')
        ->and($taskConversationPanel)->toContain('ChatEmojiPicker')
        ->and($taskConversationPanel)->toContain('@select="insertEmoji"')
        ->and($taskConversationPanel)->toContain('ref="draftTextarea"')
        ->and($taskConversationPanel)->toContain('size="icon"')
        ->and($taskConversationPanel)->toContain('absolute right-3 bottom-3 size-10 rounded-full')
        ->and($taskConversationPanel)->toContain(':aria-label="sending ? t.chat.sending : t.chat.send"')
        ->and($taskConversationPanel)->toContain('pr-28')
        ->and($consoleRoutes)->toContain('SendProjectTaskDueSoonRemindersCommand::class')
        ->and($consoleRoutes)->toContain('everyMinute()')
        ->and($consoleRoutes)->toContain('withoutOverlapping()');
});

test('visible users can open task discussion and send messages while outsiders cannot', function () {
    $creator = User::factory()->create();
    $assignee = User::factory()->create();
    $outsider = User::factory()->create();

    $task = ProjectTask::factory()->standalone()->create([
        'creator_user_id' => $creator->id,
        'assignee_user_id' => $assignee->id,
        'updated_by_user_id' => $creator->id,
        'title' => 'Task discussion test',
    ]);

    $response = $this->actingAs($creator)
        ->get(route('projects.workspace.tasks.conversation.show', $task))
        ->assertSuccessful()
        ->assertJsonPath('conversation.title', 'Task discussion test');

    $conversationId = $response->json('conversation.id');

    expect($conversationId)->toBeInt();

    $this->actingAs($creator)
        ->post(route('chats.messages.store', $conversationId), [
            'body' => 'First task update',
        ])
        ->assertSuccessful()
        ->assertJsonPath('message.body', 'First task update');

    $this->actingAs($outsider)
        ->get(route('projects.workspace.tasks.conversation.show', $task))
        ->assertNotFound();

    $this->actingAs($outsider)
        ->post(route('chats.messages.store', $conversationId), [
            'body' => 'Forbidden update',
        ])
        ->assertForbidden();
});

test('task discussion messages preserve line breaks', function () {
    $creator = User::factory()->create();
    $assignee = User::factory()->create();

    $task = ProjectTask::factory()->standalone()->create([
        'creator_user_id' => $creator->id,
        'assignee_user_id' => $assignee->id,
        'updated_by_user_id' => $creator->id,
        'title' => 'Multiline discussion',
    ]);

    $response = $this->actingAs($creator)
        ->get(route('projects.workspace.tasks.conversation.show', $task))
        ->assertSuccessful();

    $conversationId = $response->json('conversation.id');
    $body = "Update one\nUpdate two";

    $this->actingAs($creator)
        ->post(route('chats.messages.store', $conversationId), [
            'body' => $body,
        ])
        ->assertSuccessful()
        ->assertJsonPath('message.body', $body);

    $this->actingAs($creator)
        ->get(route('projects.workspace.tasks.conversation.show', $task))
        ->assertSuccessful()
        ->assertJsonPath('conversation.messages.0.body', $body);

    expect(ChatMessage::query()->latest('id')->value('body'))->toBe($body);
});
