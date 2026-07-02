<?php

namespace App\Http\Requests;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;

class StoreContactCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contact = $this->currentContact();

        return $contact !== null
            && ($this->user()?->canAccessContact($contact) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'content' => is_string($this->input('content'))
                ? trim($this->input('content'))
                : $this->input('content'),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => __('ui.contacts.comment_required'),
        ];
    }

    /**
     * @return array{content: string}
     */
    public function payload(): array
    {
        return [
            'content' => $this->validated('content'),
        ];
    }

    private function currentContact(): ?Contact
    {
        $routeContact = $this->route('contact');

        if ($routeContact instanceof Contact) {
            return $routeContact;
        }

        if (! is_scalar($routeContact)) {
            return null;
        }

        return Contact::query()->find((string) $routeContact);
    }
}
