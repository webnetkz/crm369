<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreConferenceSignalRequest extends ConferenceParticipantRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            ...$this->participantCredentialRules(),
            'target_participant_id' => ['required', 'integer'],
            'type' => ['required', 'string', Rule::in(['offer', 'answer', 'ice-candidate'])],
            'payload' => ['required', 'array:type,sdp,candidate,sdpMid,sdpMLineIndex', 'max:5'],
            'payload.type' => ['nullable', 'string', Rule::in(['offer', 'answer'])],
            'payload.sdp' => ['nullable', 'string', 'max:60000'],
            'payload.candidate' => ['nullable', 'string', 'max:8192'],
            'payload.sdpMid' => ['nullable', 'string', 'max:255'],
            'payload.sdpMLineIndex' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $type = $this->input('type');
            $payload = $this->input('payload');

            if (! is_array($payload)) {
                return;
            }

            if (in_array($type, ['offer', 'answer'], true)) {
                if (
                    array_diff(array_keys($payload), ['type', 'sdp']) !== []
                    || ($payload['type'] ?? null) !== $type
                    || ! is_string($payload['sdp'] ?? null)
                ) {
                    $validator->errors()->add('payload', __('ui.conferences.invalid_signal'));
                }

                return;
            }

            if (
                $type === 'ice-candidate'
                && (
                    array_diff(array_keys($payload), ['candidate', 'sdpMid', 'sdpMLineIndex']) !== []
                    || ! is_string($payload['candidate'] ?? null)
                )
            ) {
                $validator->errors()->add('payload', __('ui.conferences.invalid_signal'));
            }
        }];
    }

    public function targetParticipantId(): int
    {
        return (int) $this->validated('target_participant_id');
    }

    public function signalType(): string
    {
        return (string) $this->validated('type');
    }

    /** @return array<string, mixed> */
    public function signalPayload(): array
    {
        return (array) $this->validated('payload');
    }
}
