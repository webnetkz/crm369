<?php

namespace App\Http\Requests;

use App\Models\FileDirectory;
use App\Models\FileEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFileEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string|Rule>>
     */
    public function rules(): array
    {
        return [
            'directory_id' => ['required', 'integer', Rule::exists(FileDirectory::class, 'id')],
            'file' => ['nullable', 'file', 'max:20480'],
            'name' => ['nullable', 'string', 'max:255', 'regex:/^[^\/\\\\]+$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'directory_id' => is_numeric($this->input('directory_id')) ? (int) $this->input('directory_id') : null,
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
        ]);
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateEntryPayload($validator);
            },
        ];
    }

    public function directory(): FileDirectory
    {
        /** @var FileDirectory $directory */
        $directory = FileDirectory::query()->findOrFail((int) $this->validated('directory_id'));

        return $directory;
    }

    public function uploadedFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file;
    }

    public function hasUploadedFile(): bool
    {
        return $this->file('file') instanceof UploadedFile;
    }

    public function entryName(): string
    {
        if ($this->hasUploadedFile()) {
            return trim($this->uploadedFile()->getClientOriginalName());
        }

        return trim((string) $this->validated('name'));
    }

    private function validateEntryPayload(Validator $validator): void
    {
        if (! $this->hasUploadedFile() && ! is_string($this->validated('name', null))) {
            $validator->errors()->add('name', __('validation.required', ['attribute' => __('ui.files.file_name')]));

            return;
        }

        $entryName = $this->entryName();

        if ($entryName === '') {
            $validator->errors()->add(
                $this->hasUploadedFile() ? 'file' : 'name',
                __('validation.required', ['attribute' => $this->hasUploadedFile() ? __('ui.files.file_input') : __('ui.files.file_name')]),
            );

            return;
        }

        if (Str::contains($entryName, ['/', '\\'])) {
            $validator->errors()->add('name', __('validation.regex', ['attribute' => __('ui.files.file_name')]));

            return;
        }

        $exists = FileEntry::query()
            ->where('file_directory_id', $this->integer('directory_id'))
            ->whereRaw('lower(original_name) = ?', [mb_strtolower($entryName)])
            ->exists();

        if ($exists) {
            $validator->errors()->add(
                $this->hasUploadedFile() ? 'file' : 'name',
                __('validation.unique', ['attribute' => $this->hasUploadedFile() ? __('ui.files.file_input') : __('ui.files.file_name')]),
            );
        }
    }
}
