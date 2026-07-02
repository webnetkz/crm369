<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SignEdoDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'signature_payload' => ['required', 'string', 'min:32'],
            'signature_subject' => ['required', 'string', 'max:500'],
            'signature_serial_number' => ['nullable', 'string', 'max:255'],
            'signature_algorithm' => ['nullable', 'string', 'max:255'],
            'signed_payload_hash' => ['required', 'string', 'size:64'],
            'signature_metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function signatureMetadata(): ?array
    {
        $metadata = $this->validated('signature_metadata');

        return is_array($metadata) ? $metadata : null;
    }
}
