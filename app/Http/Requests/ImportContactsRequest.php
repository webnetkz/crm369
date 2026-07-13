<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\InteractsWithCsvImport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportContactsRequest extends FormRequest
{
    use InteractsWithCsvImport;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->canAccessPersonContacts() || $user->canAccessCompanyContacts();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->csvImportRules();
    }

    public function after(): array
    {
        return $this->csvImportAfter();
    }

    protected function csvDelimiterValidationKey(): string
    {
        return 'ui.contacts.csv_delimiter_invalid';
    }
}
