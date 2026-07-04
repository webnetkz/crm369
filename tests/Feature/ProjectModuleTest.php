<?php

use App\Models\ChatMessage;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskStage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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

test('tasks alias shows only standalone tasks and defaults to list view', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $user->id,
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
    ]);
    $project->members()->sync([$user->id]);

    ProjectTask::factory()->standalone()->create([
        'creator_user_id' => $user->id,
        'assignee_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        'title' => 'Inbox standalone',
    ]);
    ProjectTask::factory()->create([
        'project_id' => $project->id,
        'creator_user_id' => $user->id,
        'assignee_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        'title' => 'Project-only task',
    ]);

    $this->actingAs($user)
        ->get(route('tasks.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/Index')
            ->where('pageMode', 'tasks')
            ->where('taskDisplayMode', 'list')
            ->where('activeProject', null)
            ->has('taskGroups', 1)
            ->where('taskGroups.0.kind', 'standalone')
            ->where('taskGroups.0.tasks_count', 1)
            ->where('taskGroups.0.tasks.0.title', 'Inbox standalone')
        );
});

test('tasks alias falls back to default stages when project task stages table is missing', function () {
    $user = User::factory()->create();

    ProjectTask::factory()->standalone()->create([
        'creator_user_id' => $user->id,
        'assignee_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        'title' => 'Fallback standalone',
        'status' => ProjectTask::STATUS_TODO,
    ]);

    Schema::dropIfExists('project_task_stages');

    $this->actingAs($user)
        ->get(route('tasks.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/Index')
            ->where('pageMode', 'tasks')
            ->where('can.manageTaskStages', false)
            ->has('taskOptions.statuses', count(ProjectTaskStage::defaultStages()))
            ->where('taskOptions.statuses.0.value', ProjectTask::STATUS_TODO)
            ->where('taskOptions.statuses.3.value', ProjectTask::STATUS_DONE)
            ->where('taskGroups.0.tasks.0.title', 'Fallback standalone')
        );
});

test('standalone task can be opened in tasks mode while project task returns 404 there', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'owner_user_id' => $user->id,
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
    ]);
    $project->members()->sync([$user->id]);

    $standaloneTask = ProjectTask::factory()->standalone()->create([
        'creator_user_id' => $user->id,
        'assignee_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        'title' => 'Task mode card',
    ]);
    $projectTask = ProjectTask::factory()->create([
        'project_id' => $project->id,
        'creator_user_id' => $user->id,
        'assignee_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        'title' => 'Hidden project task',
    ]);

    $this->actingAs($user)
        ->get(route('tasks.show', ['projectTask' => $standaloneTask, 'view' => 'kanban']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/Index')
            ->where('pageMode', 'tasks')
            ->where('taskDisplayMode', 'kanban')
            ->where('activeTask.id', $standaloneTask->id)
        );

    $this->actingAs($user)
        ->get(route('tasks.show', $projectTask))
        ->assertNotFound();
});

test('standalone tasks can be exported to csv', function () {
    $user = User::factory()->create(['email' => 'owner@example.com']);
    $assignee = User::factory()->create(['email' => 'assignee@example.com']);
    $coAssignee = User::factory()->create(['email' => 'helper@example.com']);

    $parentTask = ProjectTask::factory()->standalone()->create([
        'creator_user_id' => $user->id,
        'assignee_user_id' => $assignee->id,
        'updated_by_user_id' => $user->id,
        'title' => 'Parent export task',
        'description' => 'Parent description',
        'status' => ProjectTask::STATUS_IN_PROGRESS,
        'importance' => ProjectTask::IMPORTANCE_HIGH,
        'complexity' => 6,
        'sort_order' => 1,
        'due_at' => now()->addDay()->startOfMinute(),
    ]);
    $parentTask->coAssignees()->sync([$coAssignee->id]);

    ProjectTask::factory()->standalone()->create([
        'creator_user_id' => $user->id,
        'assignee_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        'parent_task_id' => $parentTask->id,
        'title' => 'Child export task',
        'status' => ProjectTask::STATUS_TODO,
        'importance' => ProjectTask::IMPORTANCE_NORMAL,
        'complexity' => 3,
        'sort_order' => 2,
        'due_at' => null,
    ]);

    $response = $this->actingAs($user)
        ->get(route('tasks.export'))
        ->assertSuccessful()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($response->streamedContent())
        ->toContain('task_key,parent_task_key,title,description,status,importance,complexity,due_at,sort_order,assignee_email,co_assignee_emails')
        ->toContain('Parent export task')
        ->toContain('Child export task')
        ->toContain('assignee@example.com')
        ->toContain('helper@example.com');
});

test('standalone tasks can be imported from csv with parent links', function () {
    $user = User::factory()->create(['email' => 'owner@example.com']);
    $assignee = User::factory()->create(['email' => 'assignee@example.com']);
    $coAssignee = User::factory()->create(['email' => 'helper@example.com']);

    $csv = <<<'CSV'
task_key,parent_task_key,title,description,status,importance,complexity,due_at,sort_order,assignee_email,co_assignee_emails
launch,,Launch plan,Plan the release,in_progress,high,7,2026-07-03T09:00:00+00:00,2,assignee@example.com,helper@example.com
checklist,launch,Prepare checklist,,todo,normal,3,,5,,
CSV;

    $response = $this->actingAs($user)
        ->from(route('tasks.index'))
        ->post(route('tasks.import'), [
            'file' => UploadedFile::fake()->createWithContent('tasks.csv', $csv),
        ])
        ->assertRedirect(route('tasks.index'));

    $parentTask = ProjectTask::query()->where('title', 'Launch plan')->firstOrFail();
    $childTask = ProjectTask::query()->where('title', 'Prepare checklist')->firstOrFail();

    expect($parentTask->project_id)->toBeNull()
        ->and($parentTask->creator_user_id)->toBe($user->id)
        ->and($parentTask->assignee_user_id)->toBe($assignee->id)
        ->and($parentTask->coAssignees()->pluck('users.id')->all())->toBe([$coAssignee->id])
        ->and($parentTask->chatConversation()->exists())->toBeTrue()
        ->and($childTask->parent_task_id)->toBe($parentTask->id)
        ->and($childTask->project_id)->toBeNull();

    $response->assertSessionHasNoErrors();
});

test('project task csv import requires project member assignees', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $outsider = User::factory()->create(['email' => 'outsider@example.com']);

    $project = Project::factory()->create([
        'owner_user_id' => $owner->id,
        'created_by_user_id' => $owner->id,
        'updated_by_user_id' => $owner->id,
    ]);
    $project->members()->sync([$owner->id]);

    $csv = <<<'CSV'
task_key,parent_task_key,title,description,status,importance,complexity,due_at,sort_order,assignee_email,co_assignee_emails
launch,,Launch plan,Plan the release,in_progress,high,7,,2,outsider@example.com,
CSV;

    $this->actingAs($owner)
        ->from(route('projects.show', $project))
        ->post(route('projects.tasks.import', $project), [
            'file' => UploadedFile::fake()->createWithContent('project-tasks.csv', $csv),
        ])
        ->assertRedirect(route('projects.show', $project))
        ->assertSessionHasErrors('file');

    expect(ProjectTask::query()->where('project_id', $project->id)->count())->toBe(0)
        ->and($outsider->email)->toBe('outsider@example.com');
});

test('standalone task can be moved across kanban stages and is closed when moved to done', function () {
    $user = User::factory()->create();

    $task = ProjectTask::factory()->standalone()->create([
        'creator_user_id' => $user->id,
        'assignee_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        'status' => ProjectTask::STATUS_TODO,
        'completed_at' => null,
    ]);

    $this->actingAs($user)
        ->from(route('tasks.index', ['view' => 'kanban']))
        ->patch(route('projects.workspace.tasks.move', [
            'projectTask' => $task,
            'mode' => 'tasks',
            'view' => 'kanban',
        ]), [
            'status' => ProjectTask::STATUS_DONE,
        ])
        ->assertRedirect(route('tasks.index', ['view' => 'kanban']));

    $task->refresh();

    expect($task->status)->toBe(ProjectTask::STATUS_DONE)
        ->and($task->completed_at)->not->toBeNull();

    $this->actingAs($user)
        ->from(route('tasks.index', ['view' => 'kanban']))
        ->patch(route('projects.workspace.tasks.move', [
            'projectTask' => $task,
            'mode' => 'tasks',
            'view' => 'kanban',
        ]), [
            'status' => ProjectTask::STATUS_IN_PROGRESS,
        ])
        ->assertRedirect(route('tasks.index', ['view' => 'kanban']));

    $task->refresh();

    expect($task->status)->toBe(ProjectTask::STATUS_IN_PROGRESS)
        ->and($task->completed_at)->toBeNull();
});

test('user can create and rename kanban task stages', function () {
    $user = User::factory()->create();
    $todoStage = ProjectTaskStage::query()->where('slug', ProjectTaskStage::SLUG_TODO)->firstOrFail();

    $this->actingAs($user)
        ->from(route('tasks.index', ['view' => 'kanban']))
        ->post(route('projects.task-stages.store'), [
            'name' => 'Waiting client',
            'color' => '#F97316',
        ])
        ->assertRedirect(route('tasks.index', ['view' => 'kanban']));

    $customStage = ProjectTaskStage::query()->where('name', 'Waiting client')->firstOrFail();

    expect($customStage->slug)->toBe('waiting_client')
        ->and($customStage->color)->toBe('#F97316')
        ->and($customStage->is_completed)->toBeFalse();

    $this->actingAs($user)
        ->from(route('tasks.index', ['view' => 'kanban']))
        ->patch(route('projects.task-stages.update', $todoStage), [
            'name' => 'Inbox',
            'color' => '#0F766E',
        ])
        ->assertRedirect(route('tasks.index', ['view' => 'kanban']));

    $todoStage->refresh();

    expect($todoStage->name)->toBe('Inbox')
        ->and($todoStage->color)->toBe('#0F766E');

    $this->actingAs($user)
        ->get(route('tasks.index', ['view' => 'kanban']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/Index')
            ->where('taskDisplayMode', 'kanban')
            ->where('taskOptions.statuses.0.label', 'Inbox')
            ->where('taskOptions.statuses.0.color', '#0F766E')
            ->has('taskOptions.statuses', 5)
        );
});

test('user can reorder kanban task stages', function () {
    $user = User::factory()->create();
    $orderedStageIds = ProjectTaskStage::query()
        ->ordered()
        ->pluck('id')
        ->map(fn (mixed $value): int => (int) $value)
        ->values();

    $reorderedStageIds = collect([
        $orderedStageIds[1],
        $orderedStageIds[0],
        $orderedStageIds[2],
        $orderedStageIds[3],
    ]);

    $this->actingAs($user)
        ->from(route('tasks.index', ['view' => 'kanban']))
        ->patch(route('projects.task-stages.move'), [
            'stage_ids' => $reorderedStageIds->all(),
        ])
        ->assertRedirect(route('tasks.index', ['view' => 'kanban']));

    expect(ProjectTaskStage::query()->ordered()->pluck('id')->all())
        ->toBe($reorderedStageIds->all());

    $this->actingAs($user)
        ->get(route('tasks.index', ['view' => 'kanban']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/Index')
            ->where('taskDisplayMode', 'kanban')
            ->where('taskOptions.statuses.0.id', $reorderedStageIds[0])
            ->where('taskOptions.statuses.1.id', $reorderedStageIds[1])
        );
});

test('updating a task writes a change log message into the task discussion', function () {
    $creator = User::factory()->create();
    $assignee = User::factory()->create();
    $coAssignee = User::factory()->create();
    $dueAt = now()->addDay()->startOfMinute();

    $task = ProjectTask::factory()->standalone()->create([
        'creator_user_id' => $creator->id,
        'assignee_user_id' => null,
        'updated_by_user_id' => $creator->id,
        'title' => 'Initial brief',
        'description' => null,
        'status' => ProjectTask::STATUS_TODO,
        'importance' => ProjectTask::IMPORTANCE_NORMAL,
        'complexity' => 3,
        'due_at' => null,
        'sort_order' => 0,
    ]);

    $this->actingAs($creator)
        ->patch(route('projects.workspace.tasks.update', $task), [
            'project_id' => null,
            'parent_task_id' => null,
            'title' => 'Updated brief',
            'description' => 'Scope agreed with the client.',
            'status' => ProjectTask::STATUS_REVIEW,
            'importance' => ProjectTask::IMPORTANCE_HIGH,
            'complexity' => 7,
            'due_at' => $dueAt->toISOString(),
            'sort_order' => 4,
            'assignee_user_id' => $assignee->id,
            'co_assignee_user_ids' => [$coAssignee->id],
        ])
        ->assertRedirect(route('projects.workspace.tasks.show', $task));

    $conversation = $task->fresh()->chatConversation()->with('messages')->firstOrFail();
    $message = $conversation->messages()->latest('id')->firstOrFail();
    $locale = $creator->resolvedLanguage();

    expect($message->user_id)->toBe($creator->id)
        ->and($message->body)->toContain(__('ui.projects.task_change_log_heading', [], $locale))
        ->and($message->body)->toContain('- '.__('ui.projects.task_title', [], $locale).': "Initial brief" -> "Updated brief"')
        ->and($message->body)->toContain(
            '- '.__('ui.projects.status', [], $locale).': '
            .__('ui.projects.status_'.ProjectTask::STATUS_TODO, [], $locale)
            .' -> '
            .__('ui.projects.status_'.ProjectTask::STATUS_REVIEW, [], $locale),
        )
        ->and($message->body)->toContain(
            '- '.__('ui.projects.assignee', [], $locale).': '
            .__('ui.projects.unassigned', [], $locale)
            .' -> '
            .trim($assignee->name.' '.($assignee->last_name ?? '')),
        )
        ->and($message->body)->toContain(
            '- '.__('ui.projects.due_date', [], $locale).': '
            .__('ui.common.not_specified', [], $locale)
            .' -> '
            .$dueAt->format('d.m.Y H:i'),
        );
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
    $notification = $assignee->refresh()->notifications()->latest('created_at')->first();

    expect($task->title)->toBe('Prepare timeline')
        ->and($task->assignee_user_id)->toBe($assignee->id)
        ->and($task->complexity)->toBe(8)
        ->and($task->due_at?->toISOString())->toBe($dueAt->toISOString())
        ->and($task->coAssignees()->pluck('users.id')->all())->toBe([$coAssignee->id]);

    expect($notification)->not->toBeNull()
        ->and($notification?->data['title'])->toBe(
            __('ui.notifications.task_assigned_title', [], $assignee->resolvedLanguage()),
        )
        ->and($notification?->data['message'])->toBe(
            __('ui.notifications.task_assigned_message', [
                'title' => $task->title,
                'user' => trim($owner->name.' '.($owner->last_name ?? '')),
            ], $assignee->resolvedLanguage()),
        )
        ->and($notification?->data['action_url'])->toBe(route('projects.workspace.tasks.show', $task));
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
    $composer = file_get_contents(resource_path('js/components/ChatMessageComposer.vue'));
    $consoleRoutes = file_get_contents(base_path('routes/console.php'));

    expect($projectsPage)
        ->toContain('type="datetime-local"')
        ->toContain('formatDateTime')
        ->toContain('normalizeDueAtForSubmission')
        ->toContain('.transform((data) => ({')
        ->toContain('activeTaskForm')
        ->toContain('scheduleActiveTaskSave')
        ->toContain('submitActiveTaskUpdate')
        ->toContain('task_autosave_saving')
        ->toContain('SheetContent side="right"')
        ->toContain('sm:w-[80vw]')
        ->toContain('ProjectTaskConversationPanel')
        ->not->toContain('@click="openEditTask"')
        ->and($taskConversationPanel)->toContain('task_discussion_placeholder')
        ->and($taskConversationPanel)->toContain('showTaskConversation.url')
        ->and($taskConversationPanel)->toContain('storeMessage.url')
        ->and($taskConversationPanel)->toContain('ChatMessageComposer')
        ->and($taskConversationPanel)->toContain('ChatMessageAttachments')
        ->and($taskConversationPanel)->toContain("formData.append('attachments[]', attachment)")
        ->and($taskConversationPanel)->toContain('v-model:attachments="selectedAttachments"')
        ->and($composer)->toContain('ChatEmojiPicker')
        ->and($composer)->toContain('multiple')
        ->and($consoleRoutes)->toContain('SendProjectTaskDueSoonRemindersCommand::class')
        ->and($consoleRoutes)->toContain('everyMinute()')
        ->and($consoleRoutes)->toContain('withoutOverlapping()');
});

test('tasks page includes list, kanban, and gantt standalone views', function () {
    $projectsPage = file_get_contents(resource_path('js/pages/projects/Index.vue'));

    expect($projectsPage)
        ->toContain("props.taskDisplayMode === 'list'")
        ->toContain("props.taskDisplayMode === 'kanban'")
        ->toContain('ganttGridTemplateColumns')
        ->toContain('kanbanColumns')
        ->toContain('view_mode')
        ->toContain('view_gantt');
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

test('task discussion can send attachment-only messages', function () {
    Storage::fake('local');

    $creator = User::factory()->create();
    $assignee = User::factory()->create();

    $task = ProjectTask::factory()->standalone()->create([
        'creator_user_id' => $creator->id,
        'assignee_user_id' => $assignee->id,
        'updated_by_user_id' => $creator->id,
        'title' => 'Attachment discussion',
    ]);

    $conversationId = $this->actingAs($creator)
        ->get(route('projects.workspace.tasks.conversation.show', $task))
        ->assertSuccessful()
        ->json('conversation.id');

    $this->actingAs($creator)
        ->post(route('chats.messages.store', $conversationId), [
            'body' => '',
            'attachments' => [
                UploadedFile::fake()->create('task-note.pdf', 32, 'application/pdf'),
            ],
        ])
        ->assertSuccessful()
        ->assertJsonPath('message.attachments.0.name', 'task-note.pdf');

    $message = ChatMessage::query()->with('attachments')->latest('id')->firstOrFail();
    $attachment = $message->attachments->first();

    expect($message->body)->toBe('')
        ->and($attachment)->not->toBeNull();

    Storage::disk('local')->assertExists((string) $attachment?->path);

    $this->actingAs($creator)
        ->get(route('projects.workspace.tasks.conversation.show', $task))
        ->assertSuccessful()
        ->assertJsonPath('conversation.messages.0.attachments.0.name', 'task-note.pdf');
});
