<?php

namespace App\Http\Requests;

use App\Models\Contact;
use App\Models\PortalWebhook;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->route('portalWebhook') instanceof PortalWebhook) {
            return true;
        }

        $contact = $this->currentContact();

        return $contact !== null
            && ($this->user()?->canAccessContact($contact) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => $this->inputType(),
            'name' => $this->normalizeRequiredString($this->input('name')),
            'contact_person' => $this->normalizeNullableString($this->input('contact_person')),
            'position' => $this->normalizeNullableString($this->input('position')),
            'email' => $this->normalizeNullableString($this->input('email')),
            'phone' => $this->normalizeNullableString($this->input('phone')),
            'notes' => $this->normalizeNullableString($this->input('notes')),
            'company_requisites' => $this->normalizeContactRequisites($this->input('company_requisites'), $this->inputType()),
        ]);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(Contact::availableTypes())],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'company_requisites' => ['nullable', 'array'],
            'company_requisites.iin' => ['nullable', 'digits:12'],
            'company_requisites.bin' => ['nullable', 'digits:12'],
            'company_requisites.legal_address' => ['nullable', 'string', 'max:500'],
            'company_requisites.actual_address' => ['nullable', 'string', 'max:500'],
            'company_requisites.bank_name' => ['nullable', 'string', 'max:255'],
            'company_requisites.bank_bik' => ['nullable', 'string', 'max:32'],
            'company_requisites.iban' => ['nullable', 'string', 'max:34'],
            'company_requisites.kbe' => ['nullable', 'string', 'max:16'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_requisites.iin.digits' => __('ui.contacts.iin_validation'),
            'company_requisites.bin.digits' => __('ui.contacts.bin_validation'),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateUniqueRequisites($validator);
            },
        ];
    }

    /**
     * @return array{
     *     type: string,
     *     name: string,
     *     contact_person: ?string,
     *     position: ?string,
     *     email: ?string,
     *     phone: ?string,
     *     notes: ?string,
     *     company_requisites: array{
     *         iin: ?string,
     *         bin: ?string,
     *         legal_address: ?string,
     *         actual_address: ?string,
     *         bank_name: ?string,
     *         bank_bik: ?string,
     *         iban: ?string,
     *         kbe: ?string
     *     }|null
     * }
     */
    public function payload(): array
    {
        return [
            'type' => $this->validated('type'),
            'name' => $this->validated('name'),
            'contact_person' => $this->validated('contact_person'),
            'position' => $this->validated('position'),
            'email' => $this->validated('email'),
            'phone' => $this->validated('phone'),
            'notes' => $this->validated('notes'),
            'company_requisites' => $this->validated('company_requisites'),
        ];
    }

    public function contactType(): string
    {
        return $this->validated('type');
    }

    private function inputType(): ?string
    {
        $type = $this->input('type');

        return is_string($type) ? trim($type) : null;
    }

    private function normalizeRequiredString(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return array{
     *     iin: ?string,
     *     bin: ?string,
     *     legal_address: ?string,
     *     actual_address: ?string,
     *     bank_name: ?string,
     *     bank_bik: ?string,
     *     iban: ?string,
     *     kbe: ?string
     * }|null
     */
    private function normalizeContactRequisites(mixed $value, ?string $type): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $normalized = [
            'iin' => $type === Contact::TYPE_PERSON
                ? $this->normalizeNullableString($value['iin'] ?? null)
                : null,
            'bin' => $this->normalizeNullableString($value['bin'] ?? null),
            'legal_address' => $type === Contact::TYPE_COMPANY
                ? $this->normalizeNullableString($value['legal_address'] ?? null)
                : null,
            'actual_address' => $type === Contact::TYPE_COMPANY
                ? $this->normalizeNullableString($value['actual_address'] ?? null)
                : null,
            'bank_name' => $type === Contact::TYPE_COMPANY
                ? $this->normalizeNullableString($value['bank_name'] ?? null)
                : null,
            'bank_bik' => $type === Contact::TYPE_COMPANY
                ? $this->normalizeNullableString($value['bank_bik'] ?? null)
                : null,
            'iban' => $type === Contact::TYPE_COMPANY
                ? $this->normalizeNullableString($value['iban'] ?? null)
                : null,
            'kbe' => $type === Contact::TYPE_COMPANY
                ? $this->normalizeNullableString($value['kbe'] ?? null)
                : null,
        ];

        return collect($normalized)->filter(fn (?string $field): bool => $field !== null)->isEmpty()
            ? null
            : $normalized;
    }

    private function validateUniqueRequisites(Validator $validator): void
    {
        $type = $this->validated('type');

        if ($type === Contact::TYPE_COMPANY) {
            $this->validateUniqueBin($validator);

            return;
        }

        if ($type === Contact::TYPE_PERSON) {
            $this->validateUniqueIin($validator);
        }
    }

    private function validateUniqueBin(Validator $validator): void
    {
        if ($validator->errors()->has('company_requisites.bin')) {
            return;
        }

        $bin = data_get($this->validated('company_requisites') ?? [], 'bin');

        if (! is_string($bin) || $bin === '') {
            return;
        }

        $query = Contact::query()
            ->where('type', Contact::TYPE_COMPANY)
            ->where('company_requisites->bin', $bin);

        $currentContact = $this->currentContact();

        if ($currentContact instanceof Contact) {
            $query->whereKeyNot($currentContact->getKey());
        }

        if ($query->exists()) {
            $validator->errors()->add('company_requisites.bin', __('ui.contacts.bin_unique'));
        }
    }

    private function validateUniqueIin(Validator $validator): void
    {
        if ($validator->errors()->has('company_requisites.iin')) {
            return;
        }

        $iin = data_get($this->validated('company_requisites') ?? [], 'iin');

        if (! is_string($iin) || $iin === '') {
            return;
        }

        $query = Contact::query()
            ->where('type', Contact::TYPE_PERSON)
            ->where('company_requisites->iin', $iin);

        $currentContact = $this->currentContact();

        if ($currentContact instanceof Contact) {
            $query->whereKeyNot($currentContact->getKey());
        }

        if ($query->exists()) {
            $validator->errors()->add('company_requisites.iin', __('ui.contacts.iin_unique'));
        }
    }

    private function currentContact(): ?Contact
    {
        $routeContact = $this->route('contact');

        if ($routeContact instanceof Contact) {
            return $routeContact;
        }

        return is_string($routeContact) || is_int($routeContact)
            ? Contact::query()->find($routeContact)
            : null;
    }
}
