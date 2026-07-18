<?php

namespace App\Http\Requests;

use App\Models\ConferenceParticipant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ConferenceParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function participantCredentialRules(): array
    {
        return [
            'participant_id' => ['required', 'integer', Rule::exists(ConferenceParticipant::class, 'id')],
            'participant_token' => ['required', 'string', 'size:64'],
        ];
    }

    public function participantId(): int
    {
        return (int) $this->validated('participant_id');
    }

    public function participantToken(): string
    {
        return (string) $this->validated('participant_token');
    }
}
