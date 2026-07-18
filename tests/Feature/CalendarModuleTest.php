<?php

use App\Models\ApiAccessToken;
use App\Models\Conference;
use App\Models\ConferenceInvitation;
use App\Models\PortalSetting;
use App\Models\PortalWebhook;
use App\Models\ProjectTask;
use App\Models\User;
use App\Models\UserGroup;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

function calendarApiToken(User $user): string
{
    $plainTextToken = ApiAccessToken::generatePlainTextToken();

    ApiAccessToken::query()->create([
        'user_id' => $user->id,
        'name' => 'Calendar test token',
        ...ApiAccessToken::tokenAttributes($plainTextToken),
        'permissions' => [ApiAccessToken::PERMISSION_CALENDAR_READ],
    ]);

    return $plainTextToken;
}

test('calendar shows visible task deadlines and assigned conferences', function () {
    CarbonImmutable::setTestNow('2026-07-19 09:00:00');

    $user = User::factory()->create();
    $colleague = User::factory()->create();

    $visibleTask = ProjectTask::factory()->standalone()->create([
        'title' => 'Подготовить квартальный отчёт',
        'creator_user_id' => $colleague->id,
        'assignee_user_id' => $user->id,
        'due_at' => '2026-07-21 11:30:00',
    ]);

    ProjectTask::factory()->standalone()->create([
        'title' => 'Скрытая задача',
        'creator_user_id' => $colleague->id,
        'assignee_user_id' => $colleague->id,
        'due_at' => '2026-07-22 11:30:00',
    ]);

    $visibleConference = Conference::factory()->create([
        'title' => 'Встреча по отчёту',
        'created_by_user_id' => $colleague->id,
        'starts_at' => '2026-07-23 14:00:00',
    ]);

    ConferenceInvitation::factory()->create([
        'conference_id' => $visibleConference->id,
        'user_id' => $user->id,
        'invited_by_user_id' => $colleague->id,
    ]);

    Conference::factory()->create([
        'title' => 'Скрытая конференция',
        'created_by_user_id' => $colleague->id,
        'starts_at' => '2026-07-24 14:00:00',
    ]);

    $this->actingAs($user)
        ->get(route('calendar.index', [
            'from' => '2026-07-01',
            'to' => '2026-07-31',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('calendar/Index')
            ->has('events', 2)
            ->where('events.0.id', 'task:'.$visibleTask->id)
            ->where('events.0.type', 'task')
            ->where('events.1.id', 'conference:'.$visibleConference->id)
            ->where('events.1.type', 'conference')
            ->where('filters.types', ['task', 'conference'])
        );

    $this->actingAs($user)
        ->get(route('calendar.index', [
            'from' => '2026-07-01',
            'to' => '2026-07-31',
            'types' => ['conference'],
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->has('events', 1)
            ->where('events.0.id', 'conference:'.$visibleConference->id)
            ->where('filters.types', ['conference'])
        );

    CarbonImmutable::setTestNow();
});

test('calendar access can be controlled by group rights and module state', function () {
    $group = UserGroup::factory()->create([
        'permissions' => UserGroup::normalizePermissionsWithConfiguredModules([], ['calendar']),
    ]);
    $user = User::factory()->create(['user_group_id' => $group->id]);

    $this->actingAs($user)
        ->get(route('calendar.index'))
        ->assertForbidden();

    $group->update([
        'permissions' => UserGroup::normalizePermissionsWithConfiguredModules([
            UserGroup::PERMISSION_ACCESS_CALENDAR,
        ], ['calendar']),
    ]);

    $this->actingAs($user->fresh())
        ->get(route('calendar.index'))
        ->assertSuccessful();

    PortalSetting::current()->update(['disabled_modules' => ['calendar']]);

    $response = $this->actingAs($user->fresh())->get(route('dashboard'));

    expect($response->inertiaProps('auth.canAccessCalendar'))->toBeFalse()
        ->and($response->inertiaProps('menu.hiddenItems'))->toContain('calendar');

    $this->actingAs($user->fresh())
        ->get(route('calendar.index'))
        ->assertNotFound();
});

test('calendar api returns normalized events and validates the requested range', function () {
    $administrators = UserGroup::query()->firstOrCreate([
        'name' => UserGroup::ADMINISTRATORS_NAME,
    ]);
    $user = User::factory()->create([
        'user_group_id' => $administrators->id,
    ]);

    $task = ProjectTask::factory()->standalone()->create([
        'title' => 'API task',
        'creator_user_id' => $user->id,
        'assignee_user_id' => $user->id,
        'due_at' => '2026-07-20 10:00:00',
    ]);

    $token = calendarApiToken($user);
    $headers = [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ];

    $this->withHeaders($headers)
        ->getJson(route('api.v1.calendar.events.index', [
            'from' => '2026-07-01',
            'to' => '2026-07-31',
            'types' => 'task',
        ]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', 'task:'.$task->id)
        ->assertJsonPath('data.0.type', 'task')
        ->assertJsonPath('data.0.title', 'API task')
        ->assertJsonPath('meta.types.0', 'task')
        ->assertJsonPath('meta.count', 1);

    $this->withHeaders($headers)
        ->getJson(route('api.v1.calendar.events.index', [
            'from' => '2025-01-01',
            'to' => '2026-12-31',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');
});

test('calendar webhook returns only events visible to its creator', function () {
    $creator = User::factory()->create();
    $otherUser = User::factory()->create();

    $visibleConference = Conference::factory()->create([
        'title' => 'Webhook conference',
        'created_by_user_id' => $otherUser->id,
        'starts_at' => '2026-07-25 15:00:00',
    ]);

    ConferenceInvitation::factory()->create([
        'conference_id' => $visibleConference->id,
        'user_id' => $creator->id,
        'invited_by_user_id' => $otherUser->id,
    ]);

    Conference::factory()->create([
        'title' => 'Other conference',
        'created_by_user_id' => $otherUser->id,
        'starts_at' => '2026-07-26 15:00:00',
    ]);

    $webhook = PortalWebhook::factory()->create([
        'created_by_user_id' => $creator->id,
        'permissions' => [PortalWebhook::PERMISSION_CALENDAR_READ],
    ]);
    $webhook->issueToken('calendar-webhook-token');

    $this->getJson(route('portal-webhooks.calendar.events.index', [
        'portalWebhook' => $webhook,
        'from' => '2026-07-01',
        'to' => '2026-07-31',
        'types' => ['conference'],
        'token' => 'calendar-webhook-token',
    ]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', 'conference:'.$visibleConference->id)
        ->assertJsonPath('meta.user_id', $creator->id);

    $webhook->update(['permissions' => []]);

    $this->getJson(route('portal-webhooks.calendar.events.index', [
        'portalWebhook' => $webhook,
        'from' => '2026-07-01',
        'to' => '2026-07-31',
        'token' => 'calendar-webhook-token',
    ]))->assertForbidden();
});
