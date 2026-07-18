<?php

namespace App\Http\Requests;

class SyncConferenceRoomRequest extends ConferenceParticipantRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            ...$this->participantCredentialRules(),
            'signal_cursor' => ['nullable', 'integer', 'min:0'],
            'message_cursor' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function signalCursor(): int
    {
        return (int) $this->validated('signal_cursor', 0);
    }

    public function messageCursor(): int
    {
        return (int) $this->validated('message_cursor', 0);
    }
}
