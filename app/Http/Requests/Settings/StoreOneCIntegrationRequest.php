<?php

namespace App\Http\Requests\Settings;

use App\Models\OneCIntegration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOneCIntegrationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('manage-one-c') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'product' => ['required', 'string', Rule::in(OneCIntegration::products())],
            'transport' => ['required', 'string', Rule::in(OneCIntegration::transports())],
        ];
    }
}
