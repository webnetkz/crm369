<?php

namespace App\Http\Resources;

use App\Models\EdoDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiEdoDocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EdoDocument $document */
        $document = $this->resource;

        return [
            'id' => $document->id,
            'title' => $document->title,
            'external_reference' => $document->external_reference,
            'counterparty_name' => $document->counterparty_name,
            'counterparty_identifier' => $document->counterparty_identifier,
            'content' => $document->content,
            'document_source' => $document->document_source,
            'source_file_entry_id' => $document->source_file_entry_id,
            'document_file' => $document->hasDocumentFile()
                ? [
                    'original_name' => $document->document_file_name,
                    'mime_type' => $document->document_file_mime_type,
                    'size_bytes' => $document->document_file_size_bytes,
                    'download_url' => route('edo.file.download', $document),
                ]
                : null,
            'status' => $document->status,
            'public_sign_url' => $document->publicShowUrl(),
            'public_sign_expires_at' => $document->public_link_expires_at?->toISOString(),
            'has_active_public_link' => $document->hasActivePublicLink(),
            'signature_subject' => $document->signature_subject,
            'signature_serial_number' => $document->signature_serial_number,
            'signature_algorithm' => $document->signature_algorithm,
            'signed_payload_hash' => $document->signed_payload_hash,
            'signature_metadata' => $document->signature_metadata,
            'signed_at' => $document->signed_at?->toISOString(),
            'metadata' => $document->metadata,
            'created_at' => $document->created_at?->toISOString(),
            'updated_at' => $document->updated_at?->toISOString(),
            'creator' => $document->creator
                ? (new ApiUserResource($document->creator))->resolve()
                : null,
            'updater' => $document->updater
                ? (new ApiUserResource($document->updater))->resolve()
                : null,
        ];
    }
}
