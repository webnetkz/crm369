<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEdoDocumentRequest;
use App\Http\Requests\UpdateEdoDocumentRequest;
use App\Http\Resources\ApiEdoDocumentResource;
use App\Models\EdoDocument;
use App\Models\User;
use App\Support\ApiRequestContext;
use App\Support\EdoDocumentFileManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EdoDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ApiEdoDocumentResource::collection(
                $this->documentQuery(ApiRequestContext::subject($request))->get()
            )->resolve(),
        ]);
    }

    public function store(
        StoreEdoDocumentRequest $request,
        EdoDocumentFileManager $documentFileManager,
    ): JsonResponse {
        $user = ApiRequestContext::subject($request);

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

        return response()->json([
            'message' => __('ui.edo.created_success'),
            'data' => (new ApiEdoDocumentResource($this->loadDocument($document)))->resolve(),
        ], 201);
    }

    public function show(Request $request, EdoDocument $edoDocument): JsonResponse
    {
        $visibleDocument = $this->visibleDocument(ApiRequestContext::subject($request), $edoDocument);

        return response()->json([
            'data' => (new ApiEdoDocumentResource($visibleDocument))->resolve(),
        ]);
    }

    public function update(
        UpdateEdoDocumentRequest $request,
        EdoDocument $edoDocument,
        EdoDocumentFileManager $documentFileManager,
    ): JsonResponse {
        $visibleDocument = $this->visibleDocument(ApiRequestContext::subject($request), $edoDocument);
        $user = ApiRequestContext::subject($request);

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

        return response()->json([
            'message' => __('ui.edo.updated_success'),
            'data' => (new ApiEdoDocumentResource($this->loadDocument($visibleDocument->fresh())))->resolve(),
        ]);
    }

    public function destroy(Request $request, EdoDocument $edoDocument): JsonResponse
    {
        $visibleDocument = $this->visibleDocument(ApiRequestContext::subject($request), $edoDocument);

        $deletedId = $visibleDocument->id;
        $visibleDocument->delete();

        return response()->json([
            'message' => __('ui.edo.deleted_success'),
            'data' => ['id' => $deletedId],
        ]);
    }

    public function issuePublicLink(Request $request, EdoDocument $edoDocument): JsonResponse
    {
        $visibleDocument = $this->visibleDocument(ApiRequestContext::subject($request), $edoDocument);

        abort_if($visibleDocument->isSigned(), 422, __('ui.edo.already_signed'));

        $visibleDocument->issuePublicLink();

        return response()->json([
            'message' => __('ui.edo.public_link_created'),
            'data' => (new ApiEdoDocumentResource($this->loadDocument($visibleDocument->fresh())))->resolve(),
        ]);
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

    private function visibleDocument(User $user, EdoDocument $edoDocument): EdoDocument
    {
        return $this->documentQuery($user)->findOrFail($edoDocument->id);
    }

    private function loadDocument(EdoDocument $edoDocument): EdoDocument
    {
        return $edoDocument->load([
            'creator:id,name,last_name,email,user_group_id',
            'updater:id,name,last_name,email,user_group_id',
        ]);
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
}
