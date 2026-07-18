<?php

namespace App\Http\Requests;

class LeaveConferenceRoomRequest extends ConferenceParticipantRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return $this->participantCredentialRules();
    }
}
