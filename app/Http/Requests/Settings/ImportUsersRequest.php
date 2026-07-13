<?php

namespace App\Http\Requests\Settings;

use App\Http\Requests\Concerns\InteractsWithCsvImport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportUsersRequest extends FormRequest
{
    use InteractsWithCsvImport;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-user-accounts') ?? false;
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
        return 'ui.admin.csv_delimiter_invalid';
    }
}
