<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StorePortalWebhookRequest;
use App\Http\Requests\Settings\UpdatePortalWebhookRequest;
use App\Http\Resources\ApiPortalWebhookResource;
use App\Models\PortalWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalWebhookController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => PortalWebhook::query()
                ->with('creator:id,name,last_name,email,user_group_id')
                ->orderByDesc('id')
                ->get()
                ->map(fn (PortalWebhook $webhook): array => (new ApiPortalWebhookResource($webhook))->resolve())
                ->values()
                ->all(),
            'available_permissions' => collect(PortalWebhook::permissionDefinitions())
                ->map(fn (array $definition, string $key): array => [
                    'key' => $key,
                    'label' => __($definition['label_key']),
                    'description' => __($definition['description_key']),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function store(StorePortalWebhookRequest $request): JsonResponse
    {
        $plainTextToken = PortalWebhook::generatePlainTextToken();

        $webhook = PortalWebhook::create([
            'name' => $request->validated('name'),
            ...PortalWebhook::tokenAttributes($plainTextToken),
            'permissions' => $request->permissions(),
            'is_active' => $request->boolean('is_active'),
            'expires_at' => $request->expiresAt(),
            'created_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => __('ui.webhooks.created_success'),
            'data' => [
                ...(new ApiPortalWebhookResource($webhook->fresh('creator')))->resolve(),
                'plain_text_token' => $plainTextToken,
                'signed_url' => $webhook->signedUrl($plainTextToken),
            ],
        ], 201);
    }

    public function update(UpdatePortalWebhookRequest $request, PortalWebhook $portalWebhook): JsonResponse
    {
        $portalWebhook->update([
            'name' => $request->validated('name'),
            'permissions' => $request->permissions(),
            'is_active' => $request->boolean('is_active'),
            'expires_at' => $request->expiresAt(),
        ]);

        return response()->json([
            'message' => __('ui.webhooks.updated_success'),
            'data' => (new ApiPortalWebhookResource($portalWebhook->fresh('creator')))->resolve(),
        ]);
    }

    public function regenerate(Request $request, PortalWebhook $portalWebhook): JsonResponse
    {
        $plainTextToken = $portalWebhook->issueToken();

        return response()->json([
            'message' => __('ui.webhooks.regenerated_success'),
            'data' => [
                ...(new ApiPortalWebhookResource($portalWebhook->fresh('creator')))->resolve(),
                'plain_text_token' => $plainTextToken,
                'signed_url' => $portalWebhook->signedUrl($plainTextToken),
            ],
        ]);
    }

    public function destroy(PortalWebhook $portalWebhook): JsonResponse
    {
        $deletedId = $portalWebhook->id;
        $portalWebhook->delete();

        return response()->json([
            'message' => __('ui.webhooks.deleted_success'),
            'data' => [
                'id' => $deletedId,
            ],
        ]);
    }
}
