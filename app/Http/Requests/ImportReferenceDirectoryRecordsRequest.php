<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\InteractsWithCsvImport;
use App\Models\PortalWebhook;
use App\Models\ReferenceDirectory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportReferenceDirectoryRecordsRequest extends FormRequest
{
    use InteractsWithCsvImport;

    public function authorize(): bool
    {
        if ($this->route('portalWebhook') instanceof PortalWebhook) {
            return $this->route('referenceDirectory') instanceof ReferenceDirectory;
        }

        return ($this->route('referenceDirectory') instanceof ReferenceDirectory)
            && ($this->user()?->canManageDirectories() ?? false);
    }

    /**
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
        return 'ui.directories.csv_delimiter_invalid';
    }
}
