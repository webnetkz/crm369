<?php

namespace App\Http\Requests\Settings;

use App\Models\MenuItem;
use App\Support\ApiRequestContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuItemRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:80'],
            'icon' => ['nullable', 'string', Rule::in(MenuItem::availableIconKeys())],
            'url' => ['required', 'string', 'max:2048', 'regex:/^(https?:\/\/|\/)/i'],
            'opens_in_new_tab' => ['required', 'boolean'],
            'is_visible' => ['required', 'boolean'],
            'is_global' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.regex' => __('ui.menu.url_format_error'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'icon' => is_string($this->input('icon')) && trim($this->input('icon')) !== ''
                ? trim($this->input('icon'))
                : null,
            'opens_in_new_tab' => $this->boolean('opens_in_new_tab'),
            'is_visible' => $this->boolean('is_visible', true),
            'is_global' => $this->boolean('is_global'),
        ]);
    }

    public function shareWithAllUsers(): bool
    {
        return ApiRequestContext::subject($this)->canViewUsers()
            && (bool) $this->validated('is_global');
    }
}
