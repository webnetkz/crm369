<?php

namespace App\Http\Requests\Settings;

use App\Models\UserGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserGroupPermissionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(UserGroup::availablePermissions())],
        ];
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
}
