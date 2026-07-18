<?php

namespace App\Http\Requests;

class StoreConferenceMessageRequest extends ConferenceParticipantRequest
{
    protected function prepareForValidation(): void
    {
        $body = $this->input('body');

        $this->merge([
            'body' => is_string($body) ? trim($body) : $body,
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            ...$this->participantCredentialRules(),
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messageBody(): string
    {
        return (string) $this->validated('body');
    }
}
