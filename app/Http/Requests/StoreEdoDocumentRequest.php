<?php

namespace App\Http\Requests;

use App\Models\EdoDocument;
use App\Models\FileDirectory;
use App\Models\FileEntry;
use App\Support\FileAccessManager;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEdoDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $identifier = preg_replace('/\D+/', '', (string) $this->input('counterparty_identifier'));

        $this->merge([
            'title' => $this->normalizeRequiredString($this->input('title')),
            'external_reference' => $this->normalizeNullableString($this->input('external_reference')),
            'counterparty_name' => $this->normalizeRequiredString($this->input('counterparty_name')),
            'counterparty_identifier' => $identifier !== '' ? $identifier : null,
            'counterparty_email' => $this->normalizeNullableString($this->input('counterparty_email')),
            'document_source' => $this->normalizeSource($this->input('document_source')),
            'content' => $this->normalizeContent($this->input('content')),
            'selected_file_entry_id' => is_numeric($this->input('selected_file_entry_id'))
                ? (int) $this->input('selected_file_entry_id')
                : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'counterparty_name' => ['required', 'string', 'max:255'],
            'counterparty_identifier' => ['required', 'digits:12'],
            'counterparty_email' => ['nullable', 'email:rfc', 'max:255'],
            'document_source' => ['required', 'string', Rule::in(EdoDocument::availableSources())],
            'content' => ['nullable', 'string'],
            'document_upload' => ['nullable', 'file', 'max:20480'],
            'selected_file_entry_id' => ['nullable', 'integer', Rule::exists(FileEntry::class, 'id')],
            'status' => ['nullable', 'string', Rule::in([
                EdoDocument::STATUS_DRAFT,
                EdoDocument::STATUS_CANCELLED,
            ])],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateSourcePayload($validator);
            },
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function metadata(): ?array
    {
        $metadata = $this->validated('metadata');

        return is_array($metadata) ? $metadata : null;
    }

    public function documentSource(): string
    {
        return (string) $this->validated('document_source');
    }

    public function documentContent(): string
    {
        return $this->documentSource() === EdoDocument::SOURCE_TEXT
            ? (string) ($this->validated('content') ?? '')
            : '';
    }

    public function uploadedDocument(): ?UploadedFile
    {
        $file = $this->file('document_upload');

        return $file instanceof UploadedFile ? $file : null;
    }

    public function selectedFileEntry(): ?FileEntry
    {
        $fileEntryId = $this->validated('selected_file_entry_id');

        if (! is_int($fileEntryId)) {
            return null;
        }

        /** @var FileEntry|null $entry */
        $entry = FileEntry::query()->find($fileEntryId);

        return $entry;
    }

    private function normalizeRequiredString(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function normalizeSource(mixed $value): string
    {
        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : EdoDocument::SOURCE_TEXT;
    }

    private function normalizeContent(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function validateSourcePayload(Validator $validator): void
    {
        if ($this->documentSource() === EdoDocument::SOURCE_TEXT) {
            if (! is_string($this->validated('content')) || trim((string) $this->validated('content')) === '') {
                $validator->errors()->add('content', __('validation.required', ['attribute' => __('ui.edo.content_label')]));
            }

            return;
        }

        if ($this->documentSource() === EdoDocument::SOURCE_UPLOAD) {
            if ($this->uploadedDocument() === null) {
                $validator->errors()->add('document_upload', __('validation.required', ['attribute' => __('ui.edo.document_upload_label')]));
            }

            return;
        }

        if ($this->documentSource() !== EdoDocument::SOURCE_FILE_ENTRY) {
            return;
        }

        $selectedFileEntry = $this->selectedFileEntry();

        if (! $selectedFileEntry) {
            $validator->errors()->add('selected_file_entry_id', __('validation.required', ['attribute' => __('ui.edo.document_existing_label')]));

            return;
        }

        $user = $this->user();

        if (! $user) {
            return;
        }

        $directory = FileDirectory::query()
            ->with(['permissions.user', 'permissions.group'])
            ->find($selectedFileEntry->file_directory_id);

        if (! $directory) {
            $validator->errors()->add('selected_file_entry_id', __('validation.exists', ['attribute' => __('ui.edo.document_existing_label')]));

            return;
        }

        $selectedFileEntry->setRelation('directory', $directory);

        if (! app(FileAccessManager::class)->canReadEntry($user, $selectedFileEntry)) {
            $validator->errors()->add('selected_file_entry_id', __('validation.exists', ['attribute' => __('ui.edo.document_existing_label')]));
        }
    }
}
