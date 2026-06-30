<?php

namespace App\Http\Requests\Settings;

use App\Models\PortalSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModuleSettingsRequest extends FormRequest
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
            'disabled_modules' => ['nullable', 'array'],
            'disabled_modules.*' => ['string', Rule::in(PortalSetting::availableModuleKeys())],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'disabled_modules' => PortalSetting::normalizeDisabledModules($this->input('disabled_modules', [])),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function disabledModules(): array
    {
        return PortalSetting::normalizeDisabledModules($this->validated('disabled_modules', []));
    }
}
