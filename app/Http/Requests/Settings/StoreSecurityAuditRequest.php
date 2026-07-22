<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSecurityAuditRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'manual' => ['required', 'array'],
            'manual.unique_password' => ['required', 'boolean'],
            'manual.recovery_codes_stored' => ['required', 'boolean'],
            'manual.sessions_reviewed' => ['required', 'boolean'],
            'manual.device_protected' => ['required', 'boolean'],
            'manual.phishing_ready' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'manual.unique_password' => __('ui.security.audit_manual.unique_password_title'),
            'manual.recovery_codes_stored' => __('ui.security.audit_manual.recovery_codes_stored_title'),
            'manual.sessions_reviewed' => __('ui.security.audit_manual.sessions_reviewed_title'),
            'manual.device_protected' => __('ui.security.audit_manual.device_protected_title'),
            'manual.phishing_ready' => __('ui.security.audit_manual.phishing_ready_title'),
        ];
    }
}
