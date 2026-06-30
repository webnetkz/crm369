<?php

namespace App\Http\Requests;

use App\Models\FileDirectoryPermission;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFileDirectoryPermissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'access_level' => ['required', 'string', Rule::in(FileDirectoryPermission::availableAccessLevels())],
            'user_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'user_group_id' => ['nullable', 'integer', Rule::exists(UserGroup::class, 'id')],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $hasUser = $this->targetUserId() !== null;
                $hasGroup = $this->targetGroupId() !== null;

                if ($hasUser === $hasGroup) {
                    $validator->errors()->add('user_id', __('ui.files.permission_target_required'));
                }
            },
        ];
    }

    public function accessLevel(): string
    {
        return (string) $this->validated('access_level');
    }

    public function targetUserId(): ?int
    {
        $userId = $this->validated('user_id');

        return is_numeric($userId) ? (int) $userId : null;
    }

    public function targetGroupId(): ?int
    {
        $groupId = $this->validated('user_group_id');

        return is_numeric($groupId) ? (int) $groupId : null;
    }
}
