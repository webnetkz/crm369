<?php

namespace App\Http\Requests;

use App\Models\Contact;
use App\Models\PortalWebhook;
use App\Support\PerPageOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterContactsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->route('portalWebhook') instanceof PortalWebhook) {
            return true;
        }

        return $this->user()?->canAccessContacts() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->normalizeString($this->input('search')) ?? '',
            'type' => $this->normalizeString($this->input('type')) ?? 'all',
            'blacklist' => $this->normalizeString($this->input('blacklist')) ?? Contact::BLACKLIST_FILTER_ALL,
            'per_page' => (int) $this->input('per_page', PerPageOptions::DEFAULT),
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['all', ...Contact::availableTypes()])],
            'blacklist' => ['required', 'string', Rule::in(Contact::availableBlacklistFilters())],
            'per_page' => ['required', 'integer', Rule::in(PerPageOptions::allowed())],
        ];
    }

    /**
     * @return array{search: string, type: string, blacklist: string, per_page: int}
     */
    public function filters(): array
    {
        return [
            'search' => $this->validated('search') ?? '',
            'type' => $this->validated('type') ?? 'all',
            'blacklist' => $this->validated('blacklist') ?? Contact::BLACKLIST_FILTER_ALL,
            'per_page' => $this->validated('per_page') ?? PerPageOptions::DEFAULT,
        ];
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
