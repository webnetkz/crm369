<?php

namespace App\Http\Requests;

use App\Models\CrmFunnel;
use App\Models\CrmFunnelStage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrmFunnelStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $funnel = $this->route('crmFunnel');

        return $funnel instanceof CrmFunnel
            && ($this->user()?->canManageFunnel($funnel) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'color' => $this->normalizeNullableString($this->input('color')),
            'type' => is_string($this->input('type')) ? trim($this->input('type')) : CrmFunnelStage::TYPE_OPEN,
            'sort_order' => max(0, (int) $this->input('sort_order', 0)),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
            'type' => ['required', 'string', Rule::in(CrmFunnelStage::availableTypes())],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function stagePayload(): array
    {
        return [
            'name' => $this->validated('name'),
            'color' => $this->validated('color'),
            'type' => $this->validated('type'),
            'sort_order' => (int) $this->validated('sort_order'),
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
