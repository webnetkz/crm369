<?php

namespace App\Support;

use App\Http\Requests\StoreConferenceRequest;
use App\Models\Conference;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Conference lookups, creation and invitations shared by the web and native mobile clients.
 */
class ConferenceManager
{
    public function __construct(private readonly ConferenceInvitationManager $invitationManager) {}

    /**
     * The conference, or a 404 response when it does not exist.
     */
    public function findConference(string $conference): Conference
    {
        return Conference::query()->findOrFail($conference);
    }

    /**
     * The conference, or a 404 response when it does not exist or the user neither created it nor was invited to it.
     */
    public function findVisibleConference(string $conference, User $user): Conference
    {
        return Conference::query()
            ->visibleTo($user)
            ->findOrFail($conference);
    }

    /**
     * Create a conference with a fresh room and guest link and invite (and notify) the selected users.
     */
    public function create(StoreConferenceRequest $request, User $creator): Conference
    {
        return DB::transaction(function () use ($request, $creator): Conference {
            $conference = Conference::query()->create([
                ...$request->payload(),
                'room_name' => Conference::generateRoomName(),
                'public_token' => Conference::generatePublicToken(),
                'created_by_user_id' => $creator->id,
            ]);

            $this->invitationManager->inviteUsers($conference, $request->invitedUserIds(), $creator);

            return $conference;
        });
    }

    /**
     * Invite (and notify) the users that are not invited yet and return how many were invited.
     *
     * @param  array<int, int>  $userIds
     */
    public function invite(Conference $conference, array $userIds, User $actor): int
    {
        return DB::transaction(fn (): int => $this->invitationManager->inviteUsers($conference, $userIds, $actor));
    }
}
