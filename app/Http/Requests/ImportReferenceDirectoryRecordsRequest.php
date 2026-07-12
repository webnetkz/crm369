<?php

namespace App\Http\Requests;

use App\Models\ReferenceDirectory;
use App\Support\ReferenceDirectoryCsvService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class ImportReferenceDirectoryRecordsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->route('referenceDirectory') instanceof ReferenceDirectory)
            && ($this->user()?->canManageDirectories() ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'delimiter' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator): mixed => $this->validateDelimiter($validator)];
    }

    public function delimiter(): string
    {
        return ReferenceDirectoryCsvService::normalizeDelimiter($this->validated('delimiter')) ?? ';';
    }

    public function uploadedFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file;
    }

    private function validateDelimiter(Validator $validator): void
    {
        if (ReferenceDirectoryCsvService::normalizeDelimiter($this->input('delimiter')) !== null) {
            return;
        }

        $validator->errors()->add('delimiter', __('ui.directories.csv_delimiter_invalid'));
    }
}
