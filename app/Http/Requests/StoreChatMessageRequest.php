<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

class StoreChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:20480'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasMessageContent()) {
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

    public function hasMessageContent(): bool
    {
        return $this->body() !== '' || $this->hasAttachments();
    }

    public function hasAttachments(): bool
    {
        return count($this->attachments()) > 0;
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function attachments(): array
    {
        return array_values(array_filter(
            Arr::wrap($this->file('attachments')),
            fn (mixed $file): bool => $file instanceof UploadedFile,
        ));
    }
}
