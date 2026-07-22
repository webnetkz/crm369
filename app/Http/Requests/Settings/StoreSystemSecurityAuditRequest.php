<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class StoreSystemSecurityAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-system-security') === true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'manual' => ['required', 'array'],
            'manual.backups_verified' => ['required', 'boolean'],
            'manual.infrastructure_patched' => ['required', 'boolean'],
            'manual.privileged_access_reviewed' => ['required', 'boolean'],
            'manual.security_headers_configured' => ['required', 'boolean'],
            'manual.incident_plan_ready' => ['required', 'boolean'],
            'manual.secrets_rotated' => ['required', 'boolean'],
        ];
    }
}
