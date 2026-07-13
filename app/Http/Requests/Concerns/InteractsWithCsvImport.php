<?php

namespace App\Http\Requests\Concerns;

use App\Support\CsvDelimiter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

trait InteractsWithCsvImport
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function csvImportRules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'delimiter' => ['nullable', 'string', 'max:10'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    protected function csvImportAfter(): array
    {
        return [
            fn (Validator $validator): mixed => $this->validateCsvDelimiter($validator),
        ];
    }

    public function delimiter(): string
    {
        return CsvDelimiter::normalize($this->validated('delimiter')) ?? ';';
    }

    public function uploadedFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file;
    }

    abstract protected function csvDelimiterValidationKey(): string;

    private function validateCsvDelimiter(Validator $validator): void
    {
        if (CsvDelimiter::normalize($this->input('delimiter')) !== null) {
            return;
        }

        $validator->errors()->add('delimiter', __($this->csvDelimiterValidationKey()));
    }
}
