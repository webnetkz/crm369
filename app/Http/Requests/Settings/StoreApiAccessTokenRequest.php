<?php

namespace App\Http\Requests\Settings;

use App\Models\ApiAccessToken;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreApiAccessTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-api-tokens') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(ApiAccessToken::availablePermissions())],
            'never_expires' => ['sometimes', 'boolean'],
            'expires_at' => [
                Rule::requiredIf(! $this->boolean('never_expires')),
                'nullable',
                'date',
                'after:now',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $expiresAt = $this->input('expires_at');
        $hasExpiration = is_string($expiresAt)
            ? trim($expiresAt) !== ''
            : ! is_null($expiresAt);

        $this->merge([
            'never_expires' => $this->has('never_expires')
                ? $this->boolean('never_expires')
                : ! $hasExpiration,
            'permissions' => $this->normalizePermissions(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return collect($this->validated('permissions', []))
            ->filter(fn (mixed $permission): bool => is_string($permission))
            ->unique()
            ->values()
            ->all();
    }

    public function expiresAt(): ?Carbon
    {
        if ($this->validated('never_expires')) {
            return null;
        }

        $expiresAt = $this->validated('expires_at');

        return is_string($expiresAt) ? Carbon::parse($expiresAt) : null;
    }

    /**
     * @return array<int, string>
     */
    private function normalizePermissions(): array
    {
        $permissions = $this->input('permissions', []);

        if (! is_array($permissions)) {
            return [];
        }

        return collect($permissions)
            ->flatMap(function (mixed $value, mixed $key): array {
                if (is_int($key) || ctype_digit((string) $key)) {
                    return is_string($value) ? [$value] : [];
                }

                if (! is_string($key) || ! $this->isTruthyPermissionValue($value)) {
                    return [];
                }

                return [$key];
            })
            ->filter(fn (mixed $permission): bool => is_string($permission) && trim($permission) !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function isTruthyPermissionValue(mixed $value): bool
    {
        return match (true) {
            is_bool($value) => $value,
            is_int($value) => $value === 1,
            is_string($value) => in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true),
            default => false,
        };
    }
}
