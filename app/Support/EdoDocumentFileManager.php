<?php

namespace App\Support;

use App\Models\EdoDocument;
use App\Models\FileEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EdoDocumentFileManager
{
    /**
     * @return array{
     *     document_source: string,
     *     source_file_entry_id: ?int,
     *     document_file_name: ?string,
     *     document_file_disk: ?string,
     *     document_file_path: ?string,
     *     document_file_mime_type: ?string,
     *     document_file_size_bytes: ?int,
     *     document_file_hash: ?string
     * }
     */
    public function sync(
        EdoDocument $document,
        string $source,
        ?UploadedFile $uploadedFile = null,
        ?FileEntry $selectedFileEntry = null,
    ): array {
        if ($uploadedFile === null && $selectedFileEntry === null && $document->hasDocumentFile()) {
            if ($source === EdoDocument::SOURCE_UPLOAD && $document->document_source === EdoDocument::SOURCE_UPLOAD) {
                return $this->currentAttributes($document);
            }

            if ($source === EdoDocument::SOURCE_FILE_ENTRY && $document->document_source === EdoDocument::SOURCE_FILE_ENTRY) {
                return $this->currentAttributes($document);
            }
        }

        $attributes = match ($source) {
            EdoDocument::SOURCE_UPLOAD => $uploadedFile
                ? $this->storeUploadedFile($document, $uploadedFile)
                : $this->emptyAttributes(EdoDocument::SOURCE_UPLOAD),
            EdoDocument::SOURCE_FILE_ENTRY => $selectedFileEntry
                ? $this->copyFileEntry($document, $selectedFileEntry)
                : $this->emptyAttributes(EdoDocument::SOURCE_FILE_ENTRY),
            default => $this->emptyAttributes(EdoDocument::SOURCE_TEXT),
        };

        $this->deleteIfReplaced($document, $attributes['document_file_disk'], $attributes['document_file_path']);

        return $attributes;
    }

    public function deleteStoredFile(EdoDocument $document): void
    {
        if (! $document->hasDocumentFile()) {
            return;
        }

        Storage::disk((string) $document->document_file_disk)->delete((string) $document->document_file_path);
    }

    /**
     * @return array{
     *     document_source: string,
     *     source_file_entry_id: ?int,
     *     document_file_name: ?string,
     *     document_file_disk: ?string,
     *     document_file_path: ?string,
     *     document_file_mime_type: ?string,
     *     document_file_size_bytes: ?int,
     *     document_file_hash: ?string
     * }
     */
    private function storeUploadedFile(EdoDocument $document, UploadedFile $uploadedFile): array
    {
        $originalName = $this->sanitizeName($uploadedFile->getClientOriginalName());
        $storedPath = $uploadedFile->storeAs(
            'edo-documents/'.$document->id,
            $this->storedFilename($originalName),
            'local',
        );

        return [
            'document_source' => EdoDocument::SOURCE_UPLOAD,
            'source_file_entry_id' => null,
            'document_file_name' => $originalName,
            'document_file_disk' => 'local',
            'document_file_path' => $storedPath,
            'document_file_mime_type' => $uploadedFile->getClientMimeType(),
            'document_file_size_bytes' => $uploadedFile->getSize() ?? 0,
            'document_file_hash' => hash('sha256', (string) Storage::disk('local')->get($storedPath)),
        ];
    }

    /**
     * @return array{
     *     document_source: string,
     *     source_file_entry_id: ?int,
     *     document_file_name: ?string,
     *     document_file_disk: ?string,
     *     document_file_path: ?string,
     *     document_file_mime_type: ?string,
     *     document_file_size_bytes: ?int,
     *     document_file_hash: ?string
     * }
     */
    private function copyFileEntry(EdoDocument $document, FileEntry $fileEntry): array
    {
        $originalName = $this->sanitizeName($fileEntry->original_name);
        $destinationPath = 'edo-documents/'.$document->id.'/'.$this->storedFilename($originalName);
        $sourceContents = (string) Storage::disk($fileEntry->disk)->get($fileEntry->path);

        Storage::disk('local')->put($destinationPath, $sourceContents);

        return [
            'document_source' => EdoDocument::SOURCE_FILE_ENTRY,
            'source_file_entry_id' => $fileEntry->id,
            'document_file_name' => $originalName,
            'document_file_disk' => 'local',
            'document_file_path' => $destinationPath,
            'document_file_mime_type' => $fileEntry->mime_type,
            'document_file_size_bytes' => $fileEntry->size_bytes,
            'document_file_hash' => hash('sha256', $sourceContents),
        ];
    }

    private function sanitizeName(?string $name): string
    {
        $sanitized = trim(basename(str_replace('\\', '/', (string) $name)));

        return $sanitized !== '' ? $sanitized : 'document';
    }

    private function storedFilename(string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);

        return Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');
    }

    private function deleteIfReplaced(EdoDocument $document, ?string $nextDisk, ?string $nextPath): void
    {
        if (! $document->hasDocumentFile()) {
            return;
        }

        if ($document->document_file_disk === $nextDisk && $document->document_file_path === $nextPath) {
            return;
        }

        Storage::disk((string) $document->document_file_disk)->delete((string) $document->document_file_path);
    }

    /**
     * @return array{
     *     document_source: string,
     *     source_file_entry_id: ?int,
     *     document_file_name: ?string,
     *     document_file_disk: ?string,
     *     document_file_path: ?string,
     *     document_file_mime_type: ?string,
     *     document_file_size_bytes: ?int,
     *     document_file_hash: ?string
     * }
     */
    private function emptyAttributes(string $source): array
    {
        return [
            'document_source' => $source,
            'source_file_entry_id' => null,
            'document_file_name' => null,
            'document_file_disk' => null,
            'document_file_path' => null,
            'document_file_mime_type' => null,
            'document_file_size_bytes' => null,
            'document_file_hash' => null,
        ];
    }

    /**
     * @return array{
     *     document_source: string,
     *     source_file_entry_id: ?int,
     *     document_file_name: ?string,
     *     document_file_disk: ?string,
     *     document_file_path: ?string,
     *     document_file_mime_type: ?string,
     *     document_file_size_bytes: ?int,
     *     document_file_hash: ?string
     * }
     */
    private function currentAttributes(EdoDocument $document): array
    {
        return [
            'document_source' => $document->document_source,
            'source_file_entry_id' => $document->source_file_entry_id,
            'document_file_name' => $document->document_file_name,
            'document_file_disk' => $document->document_file_disk,
            'document_file_path' => $document->document_file_path,
            'document_file_mime_type' => $document->document_file_mime_type,
            'document_file_size_bytes' => $document->document_file_size_bytes,
            'document_file_hash' => $document->document_file_hash,
        ];
    }
}
