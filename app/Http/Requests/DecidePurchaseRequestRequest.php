<?php

namespace App\Http\Requests;

use App\Models\PortalWebhook;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecidePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->route('portalWebhook') instanceof PortalWebhook) {
            return true;
        }

        return $this->user()?->canApproveProcurementBudget() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('rejection_reason');

        $this->merge([
            'rejection_reason' => is_string($reason) && trim($reason) !== '' ? trim($reason) : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'rejection_reason' => ['nullable', 'required_if:decision,reject', 'string', 'max:10000'],
        ];
    }

    public function decision(): string
    {
        return $this->validated('decision');
    }

    public function rejectionReason(): ?string
    {
        return $this->validated('rejection_reason');
    }
}
