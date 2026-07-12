<?php

namespace App\Http\Requests;

use App\Models\ChatMessage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateChatMessageRequest extends FormRequest
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
            'body' => [
                'nullable',
                'string',
                'max:4000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null && (! is_string($value) || trim($value) === '')) {
                        $fail(__('validation.required', ['attribute' => $attribute]));
                    }
                },
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $body = $this->input('body');

        $this->merge([
            'body' => is_string($body)
                ? str_replace(["\r\n", "\r"], "\n", $body)
                : $body,
        ]);
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $message = $this->message();

                if ($this->body() === '' && ! $message?->attachments()->exists()) {
                    $validator->errors()->add(
                        'body',
                        __('ui.chat.message_or_attachment_required'),
                    );
                }
            },
        ];
    }

    public function body(): string
    {
        return trim((string) $this->validated('body', ''));
    }

    public function message(): ?ChatMessage
    {
        $message = $this->route('chatMessage');

        return $message instanceof ChatMessage ? $message : null;
    }
}
