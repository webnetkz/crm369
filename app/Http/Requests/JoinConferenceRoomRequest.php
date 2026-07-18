<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JoinConferenceRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $displayName = $this->input('display_name');

        $this->merge([
            'display_name' => is_string($displayName) ? trim($displayName) : $displayName,
        ]);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function displayName(): ?string
    {
        $displayName = $this->validated('display_name');

        return is_string($displayName) && $displayName !== '' ? $displayName : null;
    }
}
