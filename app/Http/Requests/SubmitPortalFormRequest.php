<?php

namespace App\Http\Requests;

use App\Models\PortalForm;
use App\Models\PortalFormField;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitPortalFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $form = $this->route('portalForm');

        return $form instanceof PortalForm && $form->is_active;
    }

    protected function prepareForValidation(): void
    {
        $values = collect((array) $this->input('values', []))
            ->map(function (mixed $value): mixed {
                if (is_string($value)) {
                    return str_replace(["\r\n", "\r"], "\n", trim($value));
                }

                return $value;
            })
            ->all();

        $this->merge([
            'values' => $values,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $form = $this->portalForm();

        $rules = [
            'values' => ['required', 'array'],
        ];

        foreach ($form->fields as $field) {
            $fieldRules = match ($field->type) {
                PortalFormField::TYPE_EMAIL => ['nullable', 'email', 'max:255'],
                PortalFormField::TYPE_NUMBER => ['nullable', 'numeric'],
                PortalFormField::TYPE_TEXTAREA => ['nullable', 'string', 'max:4000'],
                default => ['nullable', 'string', 'max:1000'],
            };

            if ($field->is_required) {
                array_unshift($fieldRules, 'required');
            }

            $rules["values.{$field->key}"] = $fieldRules;
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ($this->portalForm()->fields as $field) {
                    if (! $field->is_required) {
                        continue;
                    }

                    $value = data_get($this->validated(), "values.{$field->key}");

                    if (is_string($value) && trim($value) === '') {
                        $validator->errors()->add("values.{$field->key}", __('validation.required', ['attribute' => $field->label]));
                    }
                }
            },
        ];
    }

    public function portalForm(): PortalForm
    {
        /** @var PortalForm $form */
        $form = $this->route('portalForm');

        return $form->loadMissing('fields');
    }

    /**
     * @return array<int, array{field_id: int, key: string, label: string, type: string, value: string|null}>
     */
    public function submissionPayload(): array
    {
        $validatedValues = (array) $this->validated('values', []);

        return $this->portalForm()->fields
            ->map(function (PortalFormField $field) use ($validatedValues): array {
                $value = $validatedValues[$field->key] ?? null;

                if (is_string($value)) {
                    $value = trim($value) !== '' ? $value : null;
                }

                if (is_numeric($value)) {
                    $value = (string) $value;
                }

                return [
                    'field_id' => $field->id,
                    'key' => $field->key,
                    'label' => $field->label,
                    'type' => $field->type,
                    'value' => is_string($value) ? $value : null,
                ];
            })
            ->values()
            ->all();
    }
}
