<?php

namespace App\Http\Requests;

use App\Models\ProjectTask;
use App\Models\ProjectTaskStage;
use App\Support\ApiRequestContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveProjectTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $projectTask = $this->route('projectTask');
        $user = $this->user() !== null ? ApiRequestContext::subject($this) : null;

        return $projectTask instanceof ProjectTask
            && $user !== null
            && $user->canManageTask($projectTask);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => is_string($this->input('status'))
                ? trim($this->input('status'))
                : $this->input('status'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(ProjectTaskStage::availableSlugs())],
        ];
    }

    public function status(): string
    {
        return (string) $this->validated('status');
    }
}
