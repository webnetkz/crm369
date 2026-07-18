<?php

use App\Models\Conference;
use App\Models\ConferenceInvitation;
use App\Models\ConferenceMessage;
use App\Models\ConferenceParticipant;
use App\Models\ConferenceSignal;
use App\Models\PortalSetting;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

test('users can create conferences invite colleagues and open meeting rooms', function () {
    $creator = User::factory()->create();
    $invitee = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($creator)
        ->post(route('conferences.store'), [
            'title' => 'Еженедельный созвон',
            'description' => 'Обсуждаем задачи команды.',
            'starts_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'allow_external_guests' => true,
            'invited_user_ids' => [$invitee->id],
        ])
        ->assertRedirect();

    $conference = Conference::query()->firstOrFail();

    expect($conference->title)->toBe('Еженедельный созвон')
        ->and($conference->created_by_user_id)->toBe($creator->id)
        ->and($conference->room_name)->not->toBe('')
        ->and($conference->public_token)->not->toBe('');

    expect(ConferenceInvitation::query()
        ->where('conference_id', $conference->id)
        ->where('user_id', $invitee->id)
        ->exists())->toBeTrue();

    expect($invitee->notifications()->count())->toBe(1);

    $this->actingAs($creator)
        ->get(route('conferences.show', $conference))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('conferences/Show')
            ->where('conference.title', 'Еженедельный созвон')
            ->where('conference.external_join_url', route('conferences.public.show', [
                'conference' => $conference->public_token,
            ]))
            ->where('conference.room_key', $conference->public_token)
            ->missing('conference.embed_url')
            ->missing('conference.meeting_url')
            ->has('conference.invited_users', 1)
        );

    $this->actingAs($invitee)
        ->get(route('conferences.show', $conference))
        ->assertSuccessful();

    $this->actingAs($otherUser)
        ->get(route('conferences.show', $conference))
        ->assertNotFound();
});

test('conference index groups meetings and always allows creating another one', function () {
    $creator = User::factory()->create();

    $currentConference = Conference::factory()->create([
        'title' => 'Текущая встреча',
        'created_by_user_id' => $creator->id,
        'starts_at' => now()->subMinutes(15),
        'ended_at' => null,
    ]);

    $upcomingSoon = Conference::factory()->create([
        'title' => 'Ближайшая будущая встреча',
        'created_by_user_id' => $creator->id,
        'starts_at' => now()->addHour(),
    ]);

    $upcomingLater = Conference::factory()->create([
        'title' => 'Поздняя будущая встреча',
        'created_by_user_id' => $creator->id,
        'starts_at' => now()->addDay(),
    ]);

    $pastRecent = Conference::factory()->ended()->create([
        'title' => 'Недавняя прошедшая встреча',
        'created_by_user_id' => $creator->id,
        'starts_at' => now()->subHours(2),
        'ended_at' => now()->subHour(),
    ]);

    $pastEarlier = Conference::factory()->ended()->create([
        'title' => 'Старая прошедшая встреча',
        'created_by_user_id' => $creator->id,
        'starts_at' => now()->subDays(2),
        'ended_at' => now()->subDay(),
    ]);

    $this->actingAs($creator)
        ->get(route('conferences.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('conferences/Index')
            ->has('conferenceGroups.current', 1)
            ->where('conferenceGroups.current.0.id', $currentConference->id)
            ->has('conferenceGroups.upcoming', 2)
            ->where('conferenceGroups.upcoming.0.id', $upcomingSoon->id)
            ->where('conferenceGroups.upcoming.1.id', $upcomingLater->id)
            ->has('conferenceGroups.past', 2)
            ->where('conferenceGroups.past.0.id', $pastRecent->id)
            ->where('conferenceGroups.past.1.id', $pastEarlier->id)
        );

    $this->actingAs($creator)
        ->post(route('conferences.store'), [
            'title' => 'Ещё одна конференция',
            'starts_at' => now()->addHours(3)->format('Y-m-d\TH:i'),
            'allow_external_guests' => false,
            'invited_user_ids' => [],
        ])
        ->assertRedirect();

    expect(Conference::query()->where('title', 'Ещё одна конференция')->exists())->toBeTrue()
        ->and(Conference::query()->count())->toBe(6);
});

test('conference access can be explicitly controlled through group rights', function () {
    $group = UserGroup::factory()->create([
        'permissions' => UserGroup::normalizePermissionsWithConfiguredModules([], ['conferences']),
    ]);

    $user = User::factory()->create([
        'user_group_id' => $group->id,
    ]);

    $this->actingAs($user)
        ->get(route('conferences.index'))
        ->assertForbidden();

    $group->update([
        'permissions' => UserGroup::normalizePermissionsWithConfiguredModules([
            UserGroup::PERMISSION_ACCESS_CONFERENCES,
        ], ['conferences']),
    ]);

    $this->actingAs($user->fresh())
        ->get(route('conferences.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('conferences/Index')
            ->where('conferences', [])
            ->where('conferenceGroups', [
                'current' => [],
                'upcoming' => [],
                'past' => [],
            ])
        );
});

test('conference public join page respects guest access and module state', function () {
    $publicConference = Conference::factory()->create([
        'allow_external_guests' => true,
    ]);

    $privateConference = Conference::factory()->withoutExternalGuests()->create();

    $this->get(route('conferences.public.show', [
        'conference' => $publicConference->public_token,
    ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/conferences/Show')
            ->where('conference.title', $publicConference->title)
            ->where('conference.room_key', $publicConference->public_token)
            ->missing('conference.embed_url')
        );

    $this->get(route('conferences.public.show', [
        'conference' => $privateConference->public_token,
    ]))
        ->assertNotFound();

    PortalSetting::current()->update([
        'disabled_modules' => ['conferences'],
    ]);

    $this->get(route('conferences.public.show', [
        'conference' => $publicConference->public_token,
    ]))
        ->assertNotFound();
});

test('conference owners can invite more users and end conferences', function () {
    $creator = User::factory()->create();
    $firstInvitee = User::factory()->create();
    $secondInvitee = User::factory()->create();

    $conference = Conference::factory()->create([
        'created_by_user_id' => $creator->id,
    ]);

    $this->actingAs($creator)
        ->post(route('conferences.invitations.store', $conference), [
            'invited_user_ids' => [$firstInvitee->id, $secondInvitee->id],
        ])
        ->assertRedirect();

    expect(ConferenceInvitation::query()
        ->where('conference_id', $conference->id)
        ->count())->toBe(2);

    $this->actingAs($creator)
        ->patch(route('conferences.end', $conference))
        ->assertRedirect();

    expect($conference->fresh()->ended_at)->not->toBeNull();
});

test('conference routes return service unavailable when the conference tables are missing', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $superAdmin = User::factory()->create([
        'email' => 'super@example.com',
    ]);

    Schema::drop('conference_messages');
    Schema::drop('conference_signals');
    Schema::drop('conference_participants');
    Schema::drop('conference_invitations');
    Schema::drop('conferences');

    $this->actingAs($superAdmin)
        ->get(route('conferences.index'))
        ->assertServiceUnavailable();

    $this->actingAs($superAdmin)
        ->get(route('conferences.show', 1))
        ->assertServiceUnavailable();

    $this->get(route('conferences.public.show', [
        'conference' => 'missing-token',
    ]))
        ->assertServiceUnavailable();
});

test('guests can join a local room exchange signals and use local chat', function () {
    $conference = Conference::factory()->create([
        'allow_external_guests' => true,
        'starts_at' => now()->subMinute(),
    ]);

    $joinUrl = route('conferences.public.room.join', [
        'conference' => $conference->public_token,
    ]);

    $firstJoin = $this->postJson($joinUrl, ['display_name' => 'Guest One'])
        ->assertCreated()
        ->assertJsonPath('participant.display_name', 'Guest One')
        ->assertJsonPath('ice_servers', [])
        ->json();

    $secondJoin = $this->postJson($joinUrl, ['display_name' => 'Guest Two'])
        ->assertCreated()
        ->assertJsonCount(2, 'participants')
        ->json();

    expect($firstJoin['participant']['token'])->toHaveLength(64)
        ->and($secondJoin['participant']['token'])->toHaveLength(64)
        ->and(ConferenceParticipant::query()->count())->toBe(2);

    $this->postJson(route('conferences.public.room.signals.store', [
        'conference' => $conference->public_token,
    ]), [
        'participant_id' => $firstJoin['participant']['id'],
        'participant_token' => $firstJoin['participant']['token'],
        'target_participant_id' => $secondJoin['participant']['id'],
        'type' => 'offer',
        'payload' => [
            'type' => 'offer',
            'sdp' => 'local-session-description',
        ],
    ])->assertCreated();

    $this->postJson(route('conferences.public.room.messages.store', [
        'conference' => $conference->public_token,
    ]), [
        'participant_id' => $firstJoin['participant']['id'],
        'participant_token' => $firstJoin['participant']['token'],
        'body' => 'Локальное сообщение',
    ])->assertCreated();

    $this->postJson(route('conferences.public.room.sync', [
        'conference' => $conference->public_token,
    ]), [
        'participant_id' => $secondJoin['participant']['id'],
        'participant_token' => $secondJoin['participant']['token'],
        'signal_cursor' => 0,
        'message_cursor' => 0,
    ])
        ->assertSuccessful()
        ->assertJsonPath('signals.0.type', 'offer')
        ->assertJsonPath('signals.0.sender_participant_id', $firstJoin['participant']['id'])
        ->assertJsonPath('messages.0.body', 'Локальное сообщение');

    expect(ConferenceSignal::query()->count())->toBe(1)
        ->and(ConferenceMessage::query()->count())->toBe(1);

    $this->postJson(route('conferences.public.room.leave', [
        'conference' => $conference->public_token,
    ]), [
        'participant_id' => $firstJoin['participant']['id'],
        'participant_token' => $firstJoin['participant']['token'],
    ])->assertSuccessful();

    expect(ConferenceParticipant::query()->findOrFail($firstJoin['participant']['id'])->left_at)
        ->not->toBeNull();
});

test('local room rejects private guests ended meetings and cross room signaling', function () {
    $privateConference = Conference::factory()->withoutExternalGuests()->create();
    $endedConference = Conference::factory()->ended()->create([
        'allow_external_guests' => true,
    ]);

    $this->postJson(route('conferences.public.room.join', [
        'conference' => $privateConference->public_token,
    ]), ['display_name' => 'Outsider'])->assertNotFound();

    $this->postJson(route('conferences.public.room.join', [
        'conference' => $endedConference->public_token,
    ]), ['display_name' => 'Late guest'])->assertStatus(410);

    $firstConference = Conference::factory()->create(['allow_external_guests' => true]);
    $secondConference = Conference::factory()->create(['allow_external_guests' => true]);

    $firstParticipant = $this->postJson(route('conferences.public.room.join', [
        'conference' => $firstConference->public_token,
    ]), ['display_name' => 'First room'])->assertCreated()->json('participant');

    $secondParticipant = $this->postJson(route('conferences.public.room.join', [
        'conference' => $secondConference->public_token,
    ]), ['display_name' => 'Second room'])->assertCreated()->json('participant');

    $this->postJson(route('conferences.public.room.signals.store', [
        'conference' => $firstConference->public_token,
    ]), [
        'participant_id' => $firstParticipant['id'],
        'participant_token' => $firstParticipant['token'],
        'target_participant_id' => $secondParticipant['id'],
        'type' => 'offer',
        'payload' => ['type' => 'offer', 'sdp' => 'cross-room'],
    ])->assertNotFound();
});

test('invited users and super admins can access private local conferences', function () {
    config(['admin.super_admin_email' => 'super@example.com']);

    $creator = User::factory()->create();
    $invitee = User::factory()->create();
    $superAdmin = User::factory()->create(['email' => 'super@example.com']);
    $conference = Conference::factory()->withoutExternalGuests()->create([
        'created_by_user_id' => $creator->id,
    ]);

    ConferenceInvitation::factory()->create([
        'conference_id' => $conference->id,
        'user_id' => $invitee->id,
        'invited_by_user_id' => $creator->id,
    ]);

    $this->actingAs($invitee)
        ->postJson(route('conferences.public.room.join', [
            'conference' => $conference->public_token,
        ]))
        ->assertCreated()
        ->assertJsonPath('participant.is_guest', false);

    $this->actingAs($superAdmin)
        ->get(route('conferences.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('conferences.0.id', $conference->id)
        );
});
