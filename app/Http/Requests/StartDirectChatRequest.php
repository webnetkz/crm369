<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\ApiRequestContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StartDirectChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $recipient = User::query()->find((int) $value);
                    $currentUser = ApiRequestContext::subject($this);

                    if (! $recipient || ! $currentUser) {
                        return;
                    }

                    if ($recipient->is($currentUser)) {
                        $fail(__('ui.chat.cannot_message_yourself'));
                    }

                    if (! $recipient->is_active) {
                        $fail(__('ui.chat.user_unavailable'));
                    }
                },
            ],
        ];
    }

    public function recipient(): User
    {
        return User::query()->findOrFail((int) $this->validated('user_id'));
    }
}
