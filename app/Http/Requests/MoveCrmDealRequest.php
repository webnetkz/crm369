<?php

namespace App\Http\Requests;

use App\Models\CrmDeal;
use App\Models\CrmFunnel;
use App\Models\CrmFunnelStage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MoveCrmDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        $funnel = $this->route('crmFunnel');

        return $funnel instanceof CrmFunnel
            && ($this->user()?->canAccessFunnel($funnel) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'crm_funnel_stage_id' => is_numeric($this->input('crm_funnel_stage_id'))
                ? (int) $this->input('crm_funnel_stage_id')
                : null,
            'sort_order' => $this->filled('sort_order') ? max(0, (int) $this->input('sort_order')) : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'crm_funnel_stage_id' => ['required', 'integer', 'exists:crm_funnel_stages,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateMoveContext($validator)];
    }

    public function stageId(): int
    {
        return (int) $this->validated('crm_funnel_stage_id');
    }

    public function sortOrder(): ?int
    {
        $sortOrder = $this->validated('sort_order');

        return is_numeric($sortOrder) ? (int) $sortOrder : null;
    }

    private function validateMoveContext(Validator $validator): void
    {
        $funnel = $this->route('crmFunnel');
        $deal = $this->route('crmDeal');

        if (! $funnel instanceof CrmFunnel || ! $deal instanceof CrmDeal) {
            return;
        }

        $stage = CrmFunnelStage::query()->find($this->validated('crm_funnel_stage_id'));

        if (! $stage || $stage->crm_funnel_id !== $funnel->id || $deal->crm_funnel_id !== $funnel->id) {
            $validator->errors()->add('crm_funnel_stage_id', __('ui.funnels.validation_stage_funnel'));
        }
    }
}
