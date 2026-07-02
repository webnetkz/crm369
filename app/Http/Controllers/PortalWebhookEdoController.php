<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiEdoDocumentResource;
use App\Models\EdoDocument;
use App\Models\PortalWebhook;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PortalWebhookEdoController extends Controller
{
    public function index(Request $request, PortalWebhook $portalWebhook): JsonResponse
    {
        /** @var PortalWebhook $resolvedWebhook */
        $resolvedWebhook = $request->attributes->get('portal_webhook', $portalWebhook);

        return response()->json([
            'webhook' => [
                'id' => $resolvedWebhook->id,
                'name' => $resolvedWebhook->name,
            ],
            'data' => ApiEdoDocumentResource::collection(
                $this->documentQuery()->get()
            )->resolve(),
        ]);
    }

    public function show(Request $request, PortalWebhook $portalWebhook, EdoDocument $edoDocument): JsonResponse
    {
        /** @var PortalWebhook $resolvedWebhook */
        $resolvedWebhook = $request->attributes->get('portal_webhook', $portalWebhook);
        $visibleDocument = $this->documentQuery()->findOrFail($edoDocument->id);

        return response()->json([
            'webhook' => [
                'id' => $resolvedWebhook->id,
                'name' => $resolvedWebhook->name,
            ],
            'data' => (new ApiEdoDocumentResource($visibleDocument))->resolve(),
        ]);
    }

    public function store(Request $request, PortalWebhook $portalWebhook): JsonResponse
    {
        $validated = $this->validatedPayload($request);
        $actorId = $this->webhookActorId($request);

        $document = EdoDocument::query()->create([
            'title' => $validated['title'],
            'external_reference' => $validated['external_reference'] ?? null,
            'counterparty_name' => $validated['counterparty_name'],
            'counterparty_identifier' => $validated['counterparty_identifier'],
            'counterparty_email' => $validated['counterparty_email'] ?? null,
            'content' => $validated['content'],
            'document_source' => EdoDocument::SOURCE_TEXT,
            'status' => EdoDocument::STATUS_DRAFT,
            'created_by_user_id' => $actorId,
            'updated_by_user_id' => $actorId,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        return response()->json([
            'message' => __('ui.edo.created_success'),
            'data' => (new ApiEdoDocumentResource($this->documentQuery()->findOrFail($document->id)))->resolve(),
        ], 201);
    }

    public function update(Request $request, PortalWebhook $portalWebhook, EdoDocument $edoDocument): JsonResponse
    {
        $validated = $this->validatedPayload($request);
        $visibleDocument = $this->documentQuery()->findOrFail($edoDocument->id);
        $actorId = $this->webhookActorId($request);

        $contentWasChanged = $visibleDocument->title !== $validated['title']
            || $visibleDocument->external_reference !== ($validated['external_reference'] ?? null)
            || $visibleDocument->counterparty_name !== $validated['counterparty_name']
            || $visibleDocument->counterparty_identifier !== $validated['counterparty_identifier']
            || $visibleDocument->counterparty_email !== ($validated['counterparty_email'] ?? null)
            || $visibleDocument->content !== $validated['content']
            || $visibleDocument->metadata !== ($validated['metadata'] ?? null);

        $visibleDocument->update([
            'title' => $validated['title'],
            'external_reference' => $validated['external_reference'] ?? null,
            'counterparty_name' => $validated['counterparty_name'],
            'counterparty_identifier' => $validated['counterparty_identifier'],
            'counterparty_email' => $validated['counterparty_email'] ?? null,
            'content' => $validated['content'],
            'document_source' => EdoDocument::SOURCE_TEXT,
            'updated_by_user_id' => $actorId,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        if ($contentWasChanged && ($visibleDocument->isSigned() || $visibleDocument->hasActivePublicLink())) {
            $visibleDocument->clearSignatureState();
        }

        return response()->json([
            'message' => __('ui.edo.updated_success'),
            'data' => (new ApiEdoDocumentResource($this->documentQuery()->findOrFail($visibleDocument->id)))->resolve(),
        ]);
    }

    public function issuePublicLink(Request $request, PortalWebhook $portalWebhook, EdoDocument $edoDocument): JsonResponse
    {
        $visibleDocument = $this->documentQuery()->findOrFail($edoDocument->id);

        abort_if($visibleDocument->isSigned(), 422, __('ui.edo.already_signed'));

        $visibleDocument->issuePublicLink();

        return response()->json([
            'message' => __('ui.edo.public_link_created'),
            'data' => (new ApiEdoDocumentResource($this->documentQuery()->findOrFail($visibleDocument->id)))->resolve(),
        ]);
    }

    /**
     * @return Builder<EdoDocument>
     */
    private function documentQuery()
    {
        return EdoDocument::query()
            ->with([
                'creator:id,name,last_name,email,user_group_id',
                'updater:id,name,last_name,email,user_group_id',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request): array
    {
        return Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'counterparty_name' => ['required', 'string', 'max:255'],
            'counterparty_identifier' => ['required', 'digits:12'],
            'counterparty_email' => ['nullable', 'email:rfc', 'max:255'],
            'content' => ['required', 'string'],
            'metadata' => ['nullable', 'array'],
        ])->validate();
    }

    private function webhookActorId(Request $request): int
    {
        /** @var PortalWebhook|null $resolvedWebhook */
        $resolvedWebhook = $request->attributes->get('portal_webhook');

        if (is_int($resolvedWebhook?->created_by_user_id)) {
            return $resolvedWebhook->created_by_user_id;
        }

        $fallbackUserId = User::query()->value('id');
        abort_unless(is_int($fallbackUserId), 422, __('ui.edo.webhook_actor_missing'));

        return $fallbackUserId;
    }
}
