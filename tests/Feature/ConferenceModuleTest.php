<?php

use App\Models\Conference;
use App\Models\ConferenceInvitation;
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
            ->has('conference.invited_users', 1)
        );

    $this->actingAs($invitee)
        ->get(route('conferences.show', $conference))
        ->assertSuccessful();

    $this->actingAs($otherUser)
        ->get(route('conferences.show', $conference))
        ->assertNotFound();
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
