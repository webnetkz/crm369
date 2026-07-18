<?php

namespace App\Support;

use App\Models\Conference;
use App\Models\ConferenceInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class ConferencePageData
{
    /**
     * @param  EloquentCollection<int, Conference>  $conferences
     * @param  EloquentCollection<int, User>  $availableUsers
     * @return array<string, mixed>
     */
    public function buildIndex(EloquentCollection $conferences, EloquentCollection $availableUsers): array
    {
        $serializedConferences = $this->serializeConferences($conferences);

        return [
            'conferences' => $serializedConferences->all(),
            'conferenceGroups' => $this->groupConferences($serializedConferences),
            'availableUsers' => $availableUsers
                ->map(fn (User $user): array => $this->serializeUser($user))
                ->values()
                ->all(),
            'provider' => [
                'label' => (string) config('conference.provider_label', 'CRM369 Local WebRTC'),
            ],
        ];
    }

    /**
     * @param  EloquentCollection<int, Conference>  $conferences
     * @param  EloquentCollection<int, User>  $availableUsers
     * @return array<string, mixed>
     */
    public function buildShow(
        Conference $conference,
        EloquentCollection $conferences,
        EloquentCollection $availableUsers,
        User $viewer,
    ): array {
        $serializedConferences = $this->serializeConferences($conferences);

        return [
            'conference' => $this->serializeDetailItem($conference, $viewer),
            'conferences' => $serializedConferences->all(),
            'conferenceGroups' => $this->groupConferences($serializedConferences),
            'availableUsers' => $availableUsers
                ->map(fn (User $user): array => $this->serializeUser($user))
                ->values()
                ->all(),
            'provider' => [
                'label' => (string) config('conference.provider_label', 'CRM369 Local WebRTC'),
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
                'provider_label' => (string) config('conference.provider_label', 'CRM369 Local WebRTC'),
                'room_key' => $conference->public_token,
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
     * @param  EloquentCollection<int, Conference>  $conferences
     * @return Collection<int, array<string, mixed>>
     */
    private function serializeConferences(EloquentCollection $conferences): Collection
    {
        return $conferences
            ->map(fn (Conference $conference): array => $this->serializeListItem($conference))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $conferences
     * @return array{
     *     current: array<int, array<string, mixed>>,
     *     upcoming: array<int, array<string, mixed>>,
     *     past: array<int, array<string, mixed>>
     * }
     */
    private function groupConferences(Collection $conferences): array
    {
        return [
            'current' => $conferences
                ->where('status', Conference::STATUS_LIVE)
                ->values()
                ->all(),
            'upcoming' => $conferences
                ->where('status', Conference::STATUS_SCHEDULED)
                ->sortBy('starts_at')
                ->values()
                ->all(),
            'past' => $conferences
                ->where('status', Conference::STATUS_ENDED)
                ->sortByDesc('ended_at')
                ->values()
                ->all(),
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
            'provider_label' => (string) config('conference.provider_label', 'CRM369 Local WebRTC'),
            'room_key' => $conference->public_token,
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
}
