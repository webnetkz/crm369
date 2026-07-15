<?php

namespace App\Support;

use App\Models\Conference;
use App\Models\ConferenceInvitation;
use App\Models\User;
use App\Notifications\SystemNotification;

class ConferenceInvitationManager
{
    /**
     * @param  array<int, int>  $userIds
     */
    public function inviteUsers(Conference $conference, array $userIds, User $actor): int
    {
        $normalizedUserIds = collect($userIds)
            ->map(fn (int $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0 && $id !== $conference->created_by_user_id)
            ->unique()
            ->values()
            ->all();

        if ($normalizedUserIds === []) {
            return 0;
        }

        $existingUserIds = ConferenceInvitation::query()
            ->where('conference_id', $conference->id)
            ->whereIn('user_id', $normalizedUserIds)
            ->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $newUserIds = array_values(array_diff($normalizedUserIds, $existingUserIds));

        if ($newUserIds === []) {
            return 0;
        }

        $users = User::query()
            ->whereIn('id', $newUserIds)
            ->get(['id', 'name', 'last_name', 'email']);

        $timestamp = now();

        ConferenceInvitation::query()->insert(
            $users
                ->map(fn (User $user): array => [
                    'conference_id' => $conference->id,
                    'user_id' => $user->id,
                    'invited_by_user_id' => $actor->id,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])
                ->all(),
        );

        $actionUrl = route('conferences.show', $conference);
        $actionLabel = __('ui.conferences.open_conference');
        $title = __('ui.conferences.invitation_notification_title');

        $users->each(fn (User $user) => $user->notify(new SystemNotification(
            title: $title,
            message: __('ui.conferences.invitation_notification_message', ['title' => $conference->title]),
            actionUrl: $actionUrl,
            actionLabel: $actionLabel,
        )));

        return $users->count();
    }
}
