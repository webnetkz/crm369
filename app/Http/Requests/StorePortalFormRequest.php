<?php

namespace App\Http\Requests;

use App\Models\PortalForm;
use App\Models\PortalFormField;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePortalFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $description = $this->input('description');

        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'description' => is_string($description)
                ? str_replace(["\r\n", "\r"], "\n", trim($description))
                : null,
            'submission_mode' => is_string($this->input('submission_mode'))
                ? trim($this->input('submission_mode'))
                : $this->input('submission_mode'),
            'target_user_id' => is_numeric($this->input('target_user_id'))
                ? (int) $this->input('target_user_id')
                : null,
            'is_active' => $this->boolean('is_active', true),
            'fields' => collect((array) $this->input('fields', []))
                ->values()
                ->map(function (mixed $field, int $index): array {
                    $label = is_array($field) ? ($field['label'] ?? null) : null;
                    $placeholder = is_array($field) ? ($field['placeholder'] ?? null) : null;
                    $type = is_array($field) ? ($field['type'] ?? null) : null;
                    $id = is_array($field) ? ($field['id'] ?? null) : null;

                    return [
                        'id' => is_numeric($id) ? (int) $id : null,
                        'label' => is_string($label) ? trim($label) : null,
                        'type' => is_string($type) ? trim($type) : null,
                        'placeholder' => is_string($placeholder) && trim($placeholder) !== ''
                            ? trim($placeholder)
                            : null,
                        'is_required' => is_array($field) ? (bool) ($field['is_required'] ?? false) : false,
                        'sort_order' => ($index + 1) * 10,
                    ];
                })
                ->all(),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'submission_mode' => ['required', 'string', Rule::in(PortalForm::availableSubmissionModes())],
            'target_user_id' => ['required', 'integer', Rule::exists(User::class, 'id')->where('is_active', true)],
            'is_active' => ['required', 'boolean'],
            'fields' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'fields.*.id' => ['nullable', 'integer', Rule::exists(PortalFormField::class, 'id')],
            'fields.*.label' => ['required', 'string', 'max:120'],
            'fields.*.type' => ['required', 'string', Rule::in(PortalFormField::availableTypes())],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.is_required' => ['required', 'boolean'],
            'fields.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $form = $this->route('portalForm');
                $user = $this->user();

                if (
                    $user !== null
                    && $this->validated('submission_mode') === PortalForm::SUBMISSION_MODE_CHAT
                    && (int) $this->validated('target_user_id') === $user->id
                ) {
                    $validator->errors()->add('target_user_id', __('ui.forms.validation_target_user_chat'));
                }

                if (! $form instanceof PortalForm) {
                    return;
                }

                $fieldIds = collect($this->validated('fields', []))
                    ->pluck('id')
                    ->filter(fn (mixed $id): bool => is_int($id))
                    ->all();

                if ($fieldIds === []) {
                    return;
                }

                $invalidIds = PortalFormField::query()
                    ->whereIn('id', $fieldIds)
                    ->where('portal_form_id', '!=', $form->id)
                    ->pluck('id')
                    ->all();

                if ($invalidIds !== []) {
                    $validator->errors()->add('fields', __('ui.forms.validation_field_scope'));
                }
            },
        ];
    }

    /**
     * @return array<int, array{id: int|null, label: string, type: string, placeholder: string|null, is_required: bool, sort_order: int}>
     */
    public function fieldRows(): array
    {
        return collect($this->validated('fields', []))
            ->map(fn (array $field): array => [
                'id' => isset($field['id']) && is_numeric($field['id']) ? (int) $field['id'] : null,
                'label' => (string) $field['label'],
                'type' => (string) $field['type'],
                'placeholder' => isset($field['placeholder']) && is_string($field['placeholder']) ? $field['placeholder'] : null,
                'is_required' => (bool) $field['is_required'],
                'sort_order' => (int) $field['sort_order'],
            ])
            ->values()
            ->all();
    }
}
