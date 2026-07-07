<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'last_name' => $this->lastNameRules(),
            'middle_name' => $this->middleNameRules(),
            'email' => $this->emailRules($userId),
            'phone' => $this->phoneRules(),
            'position' => $this->positionRules(),
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'avatar_position_x' => ['sometimes', 'integer', 'between:0,100'],
            'avatar_position_y' => ['sometimes', 'integer', 'between:0,100'],
            'avatar_scale' => ['sometimes', 'numeric', 'between:0.5,3'],
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user last names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function lastNameRules(): array
    {
        return ['nullable', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user middle names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function middleNameRules(): array
    {
        return ['nullable', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user phone numbers.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function phoneRules(): array
    {
        return ['nullable', 'regex:/^\+7\d{10}$/'];
    }

    /**
     * Get the validation rules used to validate user positions.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function positionRules(): array
    {
        return ['nullable', 'string', 'max:255'];
    }
}
