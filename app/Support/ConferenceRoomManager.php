<?php

namespace App\Support;

use App\Models\Conference;
use App\Models\ConferenceMessage;
use App\Models\ConferenceParticipant;
use App\Models\ConferenceSignal;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConferenceRoomManager
{
    /** @return array<string, mixed> */
    public function join(Conference $conference, ?User $user, ?string $requestedDisplayName): array
    {
        abort_if($conference->ended_at !== null, 410, __('ui.conferences.ended_notice'));

        $displayName = $user ? $this->userDisplayName($user) : $requestedDisplayName;

        if (! is_string($displayName) || $displayName === '') {
            throw ValidationException::withMessages([
                'display_name' => __('ui.conferences.guest_name_required'),
            ]);
        }

        $this->cleanExpiredRoomState($conference);

        $token = Str::random(64);
        $participant = ConferenceParticipant::query()->create([
            'conference_id' => $conference->id,
            'user_id' => $user?->id,
            'display_name' => $displayName,
            'access_token_hash' => hash('sha256', $token),
            'is_guest' => $user === null,
            'joined_at' => now(),
            'last_seen_at' => now(),
        ]);

        if ($user !== null) {
            $conference->invitations()
                ->where('user_id', $user->id)
                ->update([
                    'joined_at' => now(),
                    'last_opened_at' => now(),
                ]);
        }

        $messages = $this->recentMessages($conference);

        return [
            'participant' => [
                ...$this->serializeParticipant($participant->load('user')),
                'token' => $token,
            ],
            'participants' => $this->activeParticipants($conference),
            'messages' => $messages->map(fn (ConferenceMessage $message): array => $this->serializeMessage($message))->all(),
            'signal_cursor' => (int) (ConferenceSignal::query()->where('conference_id', $conference->id)->max('id') ?? 0),
            'message_cursor' => (int) ($messages->last()?->id ?? 0),
            'ice_servers' => array_values((array) config('conference.ice_servers', [])),
            'poll_interval_ms' => (int) config('conference.poll_interval_ms', 1200),
        ];
    }

    /** @return array<string, mixed> */
    public function sync(
        Conference $conference,
        int $participantId,
        string $participantToken,
        int $signalCursor,
        int $messageCursor,
    ): array {
        abort_if($conference->ended_at !== null, 410, __('ui.conferences.ended_notice'));

        $participant = $this->authenticatedParticipant($conference, $participantId, $participantToken);
        $participant->update(['last_seen_at' => now()]);
        $this->cleanExpiredRoomState($conference);

        $signals = ConferenceSignal::query()
            ->where('recipient_participant_id', $participant->id)
            ->where('id', '>', $signalCursor)
            ->where('expires_at', '>', now())
            ->orderBy('id')
            ->limit(200)
            ->get();

        $messages = ConferenceMessage::query()
            ->where('conference_id', $conference->id)
            ->where('id', '>', $messageCursor)
            ->orderBy('id')
            ->limit(100)
            ->get();

        return [
            'participants' => $this->activeParticipants($conference),
            'signals' => $signals->map(fn (ConferenceSignal $signal): array => [
                'id' => $signal->id,
                'sender_participant_id' => $signal->sender_participant_id,
                'type' => $signal->type,
                'payload' => $signal->payload,
            ])->all(),
            'messages' => $messages->map(fn (ConferenceMessage $message): array => $this->serializeMessage($message))->all(),
            'signal_cursor' => (int) ($signals->last()?->id ?? $signalCursor),
            'message_cursor' => (int) ($messages->last()?->id ?? $messageCursor),
        ];
    }

    /** @param array<string, mixed> $payload */
    public function signal(
        Conference $conference,
        int $participantId,
        string $participantToken,
        int $targetParticipantId,
        string $type,
        array $payload,
    ): ConferenceSignal {
        abort_if($conference->ended_at !== null, 410, __('ui.conferences.ended_notice'));

        $participant = $this->authenticatedParticipant($conference, $participantId, $participantToken);
        abort_if($participant->id === $targetParticipantId, 422, __('ui.conferences.invalid_signal'));

        $target = ConferenceParticipant::query()
            ->where('conference_id', $conference->id)
            ->whereNull('left_at')
            ->where('last_seen_at', '>=', $this->presenceCutoff())
            ->findOrFail($targetParticipantId);

        return ConferenceSignal::query()->create([
            'conference_id' => $conference->id,
            'sender_participant_id' => $participant->id,
            'recipient_participant_id' => $target->id,
            'type' => $type,
            'payload' => $payload,
            'expires_at' => now()->addSeconds((int) config('conference.signal_ttl_seconds', 120)),
        ]);
    }

    public function message(
        Conference $conference,
        int $participantId,
        string $participantToken,
        string $body,
    ): ConferenceMessage {
        abort_if($conference->ended_at !== null, 410, __('ui.conferences.ended_notice'));

        $participant = $this->authenticatedParticipant($conference, $participantId, $participantToken);

        return ConferenceMessage::query()->create([
            'conference_id' => $conference->id,
            'participant_id' => $participant->id,
            'display_name' => $participant->display_name,
            'body' => $body,
        ]);
    }

    public function leave(Conference $conference, int $participantId, string $participantToken): void
    {
        $participant = $this->authenticatedParticipant($conference, $participantId, $participantToken, false);

        $participant->update([
            'left_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    private function authenticatedParticipant(
        Conference $conference,
        int $participantId,
        string $participantToken,
        bool $mustBeActive = true,
    ): ConferenceParticipant {
        $participant = ConferenceParticipant::query()
            ->where('conference_id', $conference->id)
            ->findOrFail($participantId);

        abort_unless($participant->tokenMatches($participantToken), 403);

        if ($mustBeActive) {
            abort_if($participant->left_at !== null || $participant->last_seen_at->lt($this->presenceCutoff()), 410);
        }

        return $participant;
    }

    /** @return array<int, array<string, mixed>> */
    private function activeParticipants(Conference $conference): array
    {
        return ConferenceParticipant::query()
            ->with('user:id,name,last_name,email,avatar_path,avatar_scale,avatar_position_x,avatar_position_y')
            ->where('conference_id', $conference->id)
            ->whereNull('left_at')
            ->where('last_seen_at', '>=', $this->presenceCutoff())
            ->orderBy('id')
            ->get()
            ->map(fn (ConferenceParticipant $participant): array => $this->serializeParticipant($participant))
            ->all();
    }

    /** @return Collection<int, ConferenceMessage> */
    private function recentMessages(Conference $conference): Collection
    {
        return ConferenceMessage::query()
            ->where('conference_id', $conference->id)
            ->orderByDesc('id')
            ->limit((int) config('conference.message_history_limit', 100))
            ->get()
            ->reverse()
            ->values();
    }

    /** @return array<string, mixed> */
    private function serializeParticipant(ConferenceParticipant $participant): array
    {
        return [
            'id' => $participant->id,
            'display_name' => $participant->display_name,
            'is_guest' => $participant->is_guest,
            'joined_at' => $participant->joined_at->toISOString(),
            'user' => $participant->user ? [
                'id' => $participant->user->id,
                'avatar' => $participant->user->avatar,
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeMessage(ConferenceMessage $message): array
    {
        return [
            'id' => $message->id,
            'participant_id' => $message->participant_id,
            'display_name' => $message->display_name,
            'body' => $message->body,
            'created_at' => $message->created_at->toISOString(),
        ];
    }

    private function cleanExpiredRoomState(Conference $conference): void
    {
        ConferenceParticipant::query()
            ->where('conference_id', $conference->id)
            ->whereNull('left_at')
            ->where('last_seen_at', '<', $this->presenceCutoff())
            ->update(['left_at' => now()]);

        ConferenceSignal::query()
            ->where('conference_id', $conference->id)
            ->where('expires_at', '<=', now())
            ->delete();
    }

    private function presenceCutoff(): CarbonInterface
    {
        return now()->subSeconds((int) config('conference.presence_timeout_seconds', 30));
    }

    private function userDisplayName(User $user): string
    {
        return collect([$user->name, $user->last_name])
            ->filter()
            ->join(' ');
    }
}
