<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEdoDocumentRequest;
use App\Http\Requests\UpdateEdoDocumentRequest;
use App\Http\Resources\ApiEdoDocumentResource;
use App\Models\EdoDocument;
use App\Models\FileDirectory;
use App\Models\FileEntry;
use App\Models\User;
use App\Support\EdoDocumentFileManager;
use App\Support\FileAccessManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class EdoDocumentController extends Controller
{
    public function index(Request $request, FileAccessManager $fileAccessManager): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $documents = $this->documentQuery($user)->get();
        $requestedDocumentId = $request->integer('document');
        $activeDocument = $request->boolean('create')
            ? null
            : ($requestedDocumentId > 0
                ? $documents->firstWhere('id', $requestedDocumentId)
                : $documents->first());

        return Inertia::render('edo/Index', [
            'documents' => ApiEdoDocumentResource::collection($documents)->resolve(),
            'activeDocument' => $activeDocument
                ? (new ApiEdoDocumentResource($activeDocument))->resolve()
                : null,
            'defaults' => [
                'title' => '',
                'external_reference' => '',
                'counterparty_name' => '',
                'counterparty_identifier' => '',
                'document_source' => EdoDocument::SOURCE_UPLOAD,
                'selected_file_entry_id' => null,
                'content' => '',
            ],
            'availableFiles' => $this->availableFiles($user, $fileAccessManager),
        ]);
    }

    public function store(
        StoreEdoDocumentRequest $request,
        EdoDocumentFileManager $documentFileManager,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $document = DB::transaction(function () use ($request, $user, $documentFileManager): EdoDocument {
            $document = EdoDocument::query()->create([
                ...$this->documentAttributes($request),
                'status' => EdoDocument::STATUS_DRAFT,
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
            ]);

            $document->update($documentFileManager->sync(
                $document,
                $request->documentSource(),
                $request->uploadedDocument(),
                $request->selectedFileEntry(),
            ));

            /** @var EdoDocument $freshDocument */
            $freshDocument = $document->fresh();

            return $freshDocument;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.edo.created_success')]);

        return to_route('edo.index', ['document' => $document->id]);
    }

    public function update(
        UpdateEdoDocumentRequest $request,
        EdoDocument $edoDocument,
        EdoDocumentFileManager $documentFileManager,
    ): RedirectResponse {
        $visibleDocument = $this->visibleDocument($request->user(), $edoDocument);
        $user = $request->user();
        abort_unless($user !== null, 401);

        $nextAttributes = [
            ...$this->documentAttributes($request),
            'updated_by_user_id' => $user->id,
        ];
        $nextFileAttributes = $documentFileManager->sync(
            $visibleDocument,
            $request->documentSource(),
            $request->uploadedDocument(),
            $request->selectedFileEntry(),
        );

        $contentWasChanged = $this->documentWasChanged($visibleDocument, $nextAttributes, $nextFileAttributes);

        $visibleDocument->update([
            ...$nextAttributes,
            ...$nextFileAttributes,
        ]);

        if ($contentWasChanged && ($visibleDocument->isSigned() || $visibleDocument->hasActivePublicLink())) {
            $visibleDocument->clearSignatureState();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.edo.updated_success')]);

        return to_route('edo.index', ['document' => $visibleDocument->id]);
    }

    public function destroy(Request $request, EdoDocument $edoDocument): RedirectResponse
    {
        $visibleDocument = $this->visibleDocument($request->user(), $edoDocument);

        $visibleDocument->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.edo.deleted_success')]);

        return to_route('edo.index');
    }

    public function issuePublicLink(Request $request, EdoDocument $edoDocument): RedirectResponse
    {
        $visibleDocument = $this->visibleDocument($request->user(), $edoDocument);

        abort_if($visibleDocument->isSigned(), 422, __('ui.edo.already_signed'));

        $visibleDocument->issuePublicLink();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.edo.public_link_created')]);

        return to_route('edo.index', ['document' => $visibleDocument->id]);
    }

    public function downloadDocumentFile(Request $request, EdoDocument $edoDocument)
    {
        $visibleDocument = $this->visibleDocument($request->user(), $edoDocument);

        abort_unless($visibleDocument->hasDocumentFile(), 404);

        return Storage::disk((string) $visibleDocument->document_file_disk)
            ->download((string) $visibleDocument->document_file_path, (string) $visibleDocument->document_file_name);
    }

    /**
     * @return Builder<EdoDocument>
     */
    private function documentQuery(User $user)
    {
        return EdoDocument::query()
            ->visibleTo($user)
            ->with([
                'creator:id,name,last_name,email,user_group_id',
                'updater:id,name,last_name,email,user_group_id',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    private function visibleDocument(?User $user, EdoDocument $edoDocument): EdoDocument
    {
        abort_unless($user !== null, 401);

        return $this->documentQuery($user)->findOrFail($edoDocument->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function documentAttributes(StoreEdoDocumentRequest|UpdateEdoDocumentRequest $request): array
    {
        return [
            'title' => (string) $request->validated('title'),
            'external_reference' => $request->validated('external_reference'),
            'counterparty_name' => (string) $request->validated('counterparty_name'),
            'counterparty_identifier' => $request->validated('counterparty_identifier'),
            'counterparty_email' => $request->validated('counterparty_email'),
            'content' => $request->documentContent(),
            'document_source' => $request->documentSource(),
            'metadata' => $request->metadata(),
        ];
    }

    /**
     * @param  array<string, mixed>  $nextAttributes
     * @param  array<string, mixed>  $nextFileAttributes
     */
    private function documentWasChanged(EdoDocument $document, array $nextAttributes, array $nextFileAttributes): bool
    {
        return $document->title !== $nextAttributes['title']
            || $document->external_reference !== $nextAttributes['external_reference']
            || $document->counterparty_name !== $nextAttributes['counterparty_name']
            || $document->counterparty_identifier !== $nextAttributes['counterparty_identifier']
            || $document->counterparty_email !== $nextAttributes['counterparty_email']
            || $document->content !== $nextAttributes['content']
            || $document->document_source !== $nextAttributes['document_source']
            || $document->source_file_entry_id !== $nextFileAttributes['source_file_entry_id']
            || $document->document_file_name !== $nextFileAttributes['document_file_name']
            || $document->document_file_hash !== $nextFileAttributes['document_file_hash']
            || $document->metadata !== $nextAttributes['metadata'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function availableFiles(User $user, FileAccessManager $fileAccessManager): array
    {
        $directories = FileDirectory::query()
            ->with(['permissions.user', 'permissions.group'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $accessibleDirectoryIds = $fileAccessManager
            ->accessibleDirectories($directories, $user)
            ->pluck('id');

        return FileEntry::query()
            ->with('directory:id,name')
            ->whereIn('file_directory_id', $accessibleDirectoryIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (FileEntry $file): array => [
                'id' => $file->id,
                'original_name' => $file->original_name,
                'directory_name' => $file->directory?->name,
                'mime_type' => $file->mime_type,
                'size_bytes' => $file->size_bytes,
                'created_at' => $file->created_at?->toISOString(),
            ])
            ->values()
            ->all();
    }
}
