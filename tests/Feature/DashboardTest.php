<?php

use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\ChatMessage;
use App\Models\PortalForm;
use App\Models\PortalFormSubmission;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    app()->setLocale('ru');

    $user = User::factory()->create([
        'language' => 'ru',
    ]);
    $project = Project::factory()->create([
        'owner_user_id' => $user->id,
        'created_by_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
    ]);
    $project->members()->sync([$user->id]);

    Project::factory()->create();

    ProjectTask::factory()->create([
        'project_id' => $project->id,
        'creator_user_id' => $user->id,
        'assignee_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        'status' => ProjectTask::STATUS_DONE,
        'completed_at' => now(),
    ]);
    ProjectTask::factory()->create([
        'project_id' => $project->id,
        'creator_user_id' => $user->id,
        'assignee_user_id' => $user->id,
        'updated_by_user_id' => $user->id,
        'status' => ProjectTask::STATUS_IN_PROGRESS,
        'due_at' => now()->subDay(),
    ]);
    ProjectTask::factory()->create();

    $conversation = ChatConversation::factory()->create([
        'type' => ChatConversation::TYPE_DIRECT,
        'created_by_user_id' => $user->id,
    ]);
    ChatConversationParticipant::factory()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $user->id,
    ]);
    ChatMessage::factory()->create([
        'chat_conversation_id' => $conversation->id,
        'user_id' => $user->id,
    ]);

    $hiddenConversation = ChatConversation::factory()->create();
    ChatMessage::factory()->create([
        'chat_conversation_id' => $hiddenConversation->id,
    ]);

    $form = PortalForm::factory()->create([
        'is_active' => true,
    ]);
    PortalFormSubmission::factory()->create([
        'portal_form_id' => $form->id,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('dashboardStats.cards', 4)
            ->has('dashboardStats.donuts', 3)
            ->has('dashboardStats.activity.series', 3)
            ->has('dashboardStats.bars.items')
            ->has('dashboardStats.radar.items')
            ->has('dashboardStats.highlights', 4)
            ->where('dashboardConfiguration.version', 1)
            ->where('dashboardConfiguration.activeDashboardId', 'overview')
            ->has('dashboardConfiguration.dashboards', 1)
            ->has('dashboardConfiguration.dashboards.0.widgets', 6)
        );

    expect(collect($response->inertiaProps('dashboardStats.cards'))->pluck('title')->all())
        ->toContain('Проекты', 'Задачи', 'Коммуникации', 'Формы');

    $donuts = collect($response->inertiaProps('dashboardStats.donuts'))->keyBy('title');

    expect($donuts['Статусы задач']['total'])->toBe(2)
        ->and($donuts['Состояние проектов']['total'])->toBe(1)
        ->and($donuts['Портальные формы']['total'])->toBe(1);
});

test('users can save their own dashboard configuration', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $configuration = [
        'version' => 1,
        'activeDashboardId' => 'sales_focus',
        'dashboards' => [
            [
                'id' => 'sales_focus',
                'name' => 'Продажи и клиенты',
                'period' => 30,
                'density' => 'compact',
                'widgets' => [
                    ['id' => 'metrics', 'visible' => true, 'size' => 'full', 'chartType' => 'cards'],
                    ['id' => 'activity', 'visible' => true, 'size' => 'full', 'chartType' => 'line'],
                    ['id' => 'donuts', 'visible' => true, 'size' => 'wide', 'chartType' => 'progress'],
                    ['id' => 'bars', 'visible' => false, 'size' => 'wide', 'chartType' => 'bars'],
                    ['id' => 'radar', 'visible' => true, 'size' => 'standard', 'chartType' => 'progress'],
                    ['id' => 'highlights', 'visible' => true, 'size' => 'full', 'chartType' => 'cards'],
                ],
            ],
        ],
    ];

    $this->actingAs($user)
        ->patch(route('dashboard.configuration.update'), [
            'configuration' => $configuration,
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasNoErrors();

    expect($user->refresh()->dashboard_configuration)
        ->toMatchArray($configuration)
        ->and($otherUser->refresh()->dashboard_configuration)->toBeNull();

    $this->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboardConfiguration.activeDashboardId', 'sales_focus')
            ->where('dashboardConfiguration.dashboards.0.period', 30)
            ->has('dashboardStats.activity.labels', 30)
        );
});

test('dashboard configuration only accepts supported and visible widgets', function () {
    $user = User::factory()->create();
    $configuration = [
        'version' => 1,
        'activeDashboardId' => 'overview',
        'dashboards' => [
            [
                'id' => 'overview',
                'name' => 'Обзор',
                'period' => 7,
                'density' => 'comfortable',
                'widgets' => [
                    ['id' => 'activity', 'visible' => false, 'size' => 'full', 'chartType' => 'radar'],
                    ['id' => 'metrics', 'visible' => false, 'size' => 'full', 'chartType' => 'cards'],
                ],
            ],
        ],
    ];

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->patch(route('dashboard.configuration.update'), [
            'configuration' => $configuration,
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors([
            'configuration.dashboards.0.widgets',
            'configuration.dashboards.0.widgets.0.chartType',
        ]);

    expect($user->refresh()->dashboard_configuration)->toBeNull();
});

test('dashboard configuration requires an existing active dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('dashboard.configuration.update'), [
            'configuration' => [
                'version' => 1,
                'activeDashboardId' => 'missing',
                'dashboards' => [
                    [
                        'id' => 'overview',
                        'name' => 'Обзор',
                        'period' => 7,
                        'density' => 'comfortable',
                        'widgets' => [
                            ['id' => 'metrics', 'visible' => true, 'size' => 'full', 'chartType' => 'cards'],
                        ],
                    ],
                ],
            ],
        ])
        ->assertSessionHasErrors('configuration.activeDashboardId');
});

test('dashboard stays available before the system key migration is applied', function () {
    ChatConversation::flushSystemKeySupportCache();

    Schema::table('chat_conversations', function (Blueprint $table) {
        $table->dropUnique(['system_key']);
        $table->dropColumn('system_key');
    });

    ChatConversation::flushSystemKeySupportCache();

    try {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSuccessful()
            ->assertInertia(fn (Assert $page) => $page->where('chat.unreadCount', 0));
    } finally {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->string('system_key')->nullable()->unique()->after('type');
        });

        ChatConversation::flushSystemKeySupportCache();
    }
});
