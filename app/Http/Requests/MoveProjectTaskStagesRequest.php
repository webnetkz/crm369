<?php

namespace App\Http\Requests;

use App\Models\ProjectTaskStage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MoveProjectTaskStagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'stage_ids' => array_values(array_filter(
                (array) $this->input('stage_ids', []),
                fn (mixed $value): bool => is_numeric($value) && (int) $value > 0,
            )),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'stage_ids' => ['required', 'array', 'list'],
            'stage_ids.*' => ['integer', 'distinct:strict', 'exists:project_task_stages,id'],
        ];
    }

    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateAllStagesArePresent($validator),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function stageIds(): array
    {
        return collect($this->validated('stage_ids', []))
            ->map(fn (mixed $value): int => (int) $value)
            ->values()
            ->all();
    }

    private function validateAllStagesArePresent(Validator $validator): void
    {
        $submittedStageIds = $this->stageIds();
        $existingStageIds = ProjectTaskStage::query()
            ->ordered()
            ->pluck('id')
            ->map(fn (mixed $value): int => (int) $value)
            ->values()
            ->all();

        if ($submittedStageIds === [] || $submittedStageIds === $existingStageIds) {
            return;
        }

        if (count($submittedStageIds) !== count($existingStageIds)) {
            $validator->errors()->add('stage_ids', __('ui.projects.validation_stage_order'));

            return;
        }

        $sortedSubmitted = $submittedStageIds;
        $sortedExisting = $existingStageIds;

        sort($sortedSubmitted);
        sort($sortedExisting);

        if ($sortedSubmitted !== $sortedExisting) {
            $validator->errors()->add('stage_ids', __('ui.projects.validation_stage_order'));
        }
    }
}
