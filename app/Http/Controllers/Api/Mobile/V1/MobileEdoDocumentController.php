<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\EdoDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MobileEdoDocumentController extends Controller
{
    /**
     * Download the attached file of a document visible to the user, as an attachment named after the original file name.
     */
    public function downloadFile(Request $request, EdoDocument $edoDocument): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $visibleDocument = EdoDocument::query()
            ->visibleTo($user)
            ->findOrFail($edoDocument->id);

        abort_unless($visibleDocument->hasDocumentFile(), 404);

        return Storage::disk((string) $visibleDocument->document_file_disk)
            ->download((string) $visibleDocument->document_file_path, (string) $visibleDocument->document_file_name);
    }
}
