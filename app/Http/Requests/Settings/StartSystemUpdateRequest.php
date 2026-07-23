<?php

namespace App\Http\Requests\Settings;

use App\Actions\SystemUpdates\StartSystemUpdate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartSystemUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-system-updates') === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'component' => ['required', 'string', Rule::in(StartSystemUpdate::COMPONENTS)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'component' => $this->route('component'),
        ]);
    }
}
