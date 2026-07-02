<?php

namespace App\Http\Controllers;

use App\Http\Requests\SignEdoDocumentRequest;
use App\Models\EdoDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PublicEdoSigningController extends Controller
{
    public function show(Request $request, EdoDocument $edoDocument): Response
    {
        return Inertia::render('public/edo/Show', [
            'document' => [
                'id' => $edoDocument->id,
                'title' => $edoDocument->title,
                'external_reference' => $edoDocument->external_reference,
                'counterparty_name' => $edoDocument->counterparty_name,
                'counterparty_identifier' => $edoDocument->counterparty_identifier,
                'content' => $edoDocument->content,
                'document_source' => $edoDocument->document_source,
                'document_file' => $edoDocument->hasDocumentFile()
                    ? [
                        'original_name' => $edoDocument->document_file_name,
                        'mime_type' => $edoDocument->document_file_mime_type,
                        'size_bytes' => $edoDocument->document_file_size_bytes,
                    ]
                    : null,
                'document_file_download_url' => $edoDocument->publicDownloadUrl(),
                'status' => $edoDocument->status,
                'public_link_expires_at' => $edoDocument->public_link_expires_at?->toISOString(),
                'signed_at' => $edoDocument->signed_at?->toISOString(),
                'signature_subject' => $edoDocument->signature_subject,
                'signature_algorithm' => $edoDocument->signature_algorithm,
                'signed_payload_hash' => $edoDocument->signingPayloadHash(),
                'sign_payload_xml' => $edoDocument->signingPayload(),
                'submit_url' => $edoDocument->publicSignUrl(),
                'state' => $this->publicState($request, $edoDocument),
            ],
        ]);
    }

    public function sign(SignEdoDocumentRequest $request, EdoDocument $edoDocument): RedirectResponse
    {
        abort_if($this->publicState($request, $edoDocument) !== 'ready', 403);
        abort_unless(
            hash_equals($edoDocument->signingPayloadHash(), (string) $request->validated('signed_payload_hash')),
            422,
            __('ui.edo.payload_outdated'),
        );

        $edoDocument->markSigned(
            signaturePayload: (string) $request->validated('signature_payload'),
            signatureSubject: (string) $request->validated('signature_subject'),
            signatureSerialNumber: $request->validated('signature_serial_number'),
            signatureAlgorithm: $request->validated('signature_algorithm'),
            signatureMetadata: $request->signatureMetadata(),
        );

        return back();
    }

    public function download(Request $request, EdoDocument $edoDocument)
    {
        abort_if($this->publicState($request, $edoDocument) === 'expired', 403);
        abort_unless($edoDocument->hasDocumentFile(), 404);

        return Storage::disk((string) $edoDocument->document_file_disk)
            ->download((string) $edoDocument->document_file_path, (string) $edoDocument->document_file_name);
    }

    private function publicState(Request $request, EdoDocument $edoDocument): string
    {
        if (! $request->hasValidSignature()) {
            return 'expired';
        }

        if (! $edoDocument->hasActivePublicLink()) {
            return 'expired';
        }

        if ($edoDocument->isSigned()) {
            return 'signed';
        }

        return 'ready';
    }
}
