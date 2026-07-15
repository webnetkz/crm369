<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessConferences() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => $this->normalizeRequiredString($this->input('title')),
            'description' => $this->normalizeNullableString($this->input('description')),
            'starts_at' => $this->normalizeNullableString($this->input('starts_at')),
            'allow_external_guests' => $this->boolean('allow_external_guests', true),
            'invited_user_ids' => $this->normalizeUserIds($this->input('invited_user_ids', [])),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'starts_at' => ['nullable', 'date'],
            'allow_external_guests' => ['required', 'boolean'],
            'invited_user_ids' => ['nullable', 'array'],
            'invited_user_ids.*' => ['integer', Rule::exists(User::class, 'id')],
        ];
    }

    /**
     * @return array{
     *     title: string,
     *     description: ?string,
     *     starts_at: ?string,
     *     allow_external_guests: bool
     * }
     */
    public function payload(): array
    {
        return [
            'title' => $this->validated('title'),
            'description' => $this->validated('description'),
            'starts_at' => $this->validated('starts_at'),
            'allow_external_guests' => (bool) $this->validated('allow_external_guests'),
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

    private function normalizeRequiredString(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
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
