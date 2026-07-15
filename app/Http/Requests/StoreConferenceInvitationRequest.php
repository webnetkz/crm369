<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConferenceInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessConferences() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'invited_user_ids' => $this->normalizeUserIds($this->input('invited_user_ids', [])),
        ]);
    }

    public function rules(): array
    {
        return [
            'invited_user_ids' => ['required', 'array', 'min:1'],
            'invited_user_ids.*' => ['integer', Rule::exists(User::class, 'id')],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function invitedUserIds(): array
    {
        return collect($this->validated('invited_user_ids', []))
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function normalizeUserIds(mixed $value): array
    {
        return collect(is_array($value) ? $value : [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
