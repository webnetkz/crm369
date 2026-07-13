<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\InteractsWithCsvImport;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportProjectTasksRequest extends FormRequest
{
    use InteractsWithCsvImport;

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $project = $this->route('project');

        if (! $project instanceof Project) {
            return true;
        }

        return $user->canWorkOnProject($project);
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
        return 'ui.projects.csv_delimiter_invalid';
    }
}
