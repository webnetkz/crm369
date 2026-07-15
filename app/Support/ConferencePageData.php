<?php

namespace App\Support;

use App\Models\Conference;
use App\Models\ConferenceInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ConferencePageData
{
    /**
     * @param  Collection<int, Conference>  $conferences
     * @param  Collection<int, User>  $availableUsers
     * @return array<string, mixed>
     */
    public function buildIndex(Collection $conferences, Collection $availableUsers): array
    {
        return [
            'conferences' => $conferences
                ->map(fn (Conference $conference): array => $this->serializeListItem($conference))
                ->values()
                ->all(),
            'availableUsers' => $availableUsers
                ->map(fn (User $user): array => $this->serializeUser($user))
                ->values()
                ->all(),
            'provider' => [
                'label' => (string) config('conference.provider_label', 'Jitsi Meet'),
            ],
        ];
    }

    /**
     * @param  Collection<int, Conference>  $conferences
     * @param  Collection<int, User>  $availableUsers
     * @return array<string, mixed>
     */
    public function buildShow(Conference $conference, Collection $conferences, Collection $availableUsers, User $viewer): array
    {
        return [
            'conference' => $this->serializeDetailItem($conference, $viewer),
            'conferences' => $conferences
                ->map(fn (Conference $listConference): array => $this->serializeListItem($listConference))
                ->values()
                ->all(),
            'availableUsers' => $availableUsers
                ->map(fn (User $user): array => $this->serializeUser($user))
                ->values()
                ->all(),
            'provider' => [
                'label' => (string) config('conference.provider_label', 'Jitsi Meet'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPublicShow(Conference $conference): array
    {
        return [
            'conference' => [
                'id' => $conference->id,
                'title' => $conference->title,
                'description' => $conference->description,
                'starts_at' => $conference->starts_at?->toISOString(),
                'ended_at' => $conference->ended_at?->toISOString(),
                'status' => $conference->status(),
                'provider_label' => (string) config('conference.provider_label', 'Jitsi Meet'),
                'embed_url' => $this->meetingUrl($conference, true),
                'meeting_url' => $this->meetingUrl($conference, true),
                'creator' => $conference->creator
                    ? [
                        'id' => $conference->creator->id,
                        'name' => $conference->creator->name,
                        'last_name' => $conference->creator->last_name,
                        'email' => $conference->creator->email,
                    ]
                    : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeListItem(Conference $conference): array
    {
        return [
            'id' => $conference->id,
            'title' => $conference->title,
            'description' => $conference->description,
            'starts_at' => $conference->starts_at?->toISOString(),
            'ended_at' => $conference->ended_at?->toISOString(),
            'status' => $conference->status(),
            'allow_external_guests' => $conference->allow_external_guests,
            'external_join_url' => $conference->allowsPublicJoin()
                ? route('conferences.public.show', ['conference' => $conference->public_token])
                : null,
            'invited_users_count' => $conference->relationLoaded('invitations')
                ? $conference->invitations->count()
                : $conference->invitations()->count(),
            'creator' => $conference->creator
                ? [
                    'id' => $conference->creator->id,
                    'name' => $conference->creator->name,
                    'last_name' => $conference->creator->last_name,
                    'email' => $conference->creator->email,
                ]
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDetailItem(Conference $conference, User $viewer): array
    {
        return [
            ...$this->serializeListItem($conference),
            'public_token' => $conference->public_token,
            'room_name' => $conference->room_name,
            'provider_label' => (string) config('conference.provider_label', 'Jitsi Meet'),
            'embed_url' => $this->meetingUrl($conference, false),
            'meeting_url' => $this->meetingUrl($conference, false),
            'can' => [
                'manage' => $conference->canBeManagedBy($viewer),
                'invite' => $conference->canBeManagedBy($viewer),
            ],
            'invited_users' => $conference->invitations
                ->map(fn (ConferenceInvitation $invitation): array => [
                    'id' => $invitation->id,
                    'invited_at' => $invitation->created_at?->toISOString(),
                    'joined_at' => $invitation->joined_at?->toISOString(),
                    'last_opened_at' => $invitation->last_opened_at?->toISOString(),
                    'user' => $invitation->user ? $this->serializeUser($invitation->user) : null,
                ])
                ->filter(fn (array $invitation): bool => $invitation['user'] !== null)
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'avatar_position_x' => $user->avatar_position_x,
            'avatar_position_y' => $user->avatar_position_y,
            'avatar_scale' => $user->avatar_scale,
        ];
    }

    private function meetingUrl(Conference $conference, bool $prejoinEnabled): string
    {
        $baseUrl = rtrim((string) config('conference.embed_base_url', 'https://meet.jit.si'), '/');
        $hash = http_build_query([
            'config.prejoinPageEnabled' => $prejoinEnabled ? 'true' : 'false',
        ]);

        return $baseUrl.'/'.rawurlencode($conference->room_name).($hash !== '' ? '#'.$hash : '');
    }
}
