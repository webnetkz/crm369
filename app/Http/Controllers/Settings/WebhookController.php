<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StorePortalWebhookRequest;
use App\Http\Requests\Settings\UpdatePortalWebhookRequest;
use App\Models\PortalWebhook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebhookController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/Webhooks', [
            'webhooks' => PortalWebhook::query()
                ->with('creator:id,name,last_name,email')
                ->orderByDesc('id')
                ->get()
                ->map(fn (PortalWebhook $webhook): array => $this->serializeWebhook($webhook))
                ->values(),
            'availablePermissions' => collect(PortalWebhook::permissionDefinitions())
                ->map(fn (array $definition, string $key): array => [
                    'key' => $key,
                    'label' => __($definition['label_key']),
                    'description' => __($definition['description_key']),
                ])
                ->values(),
        ]);
    }

    public function store(StorePortalWebhookRequest $request): RedirectResponse
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

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.webhooks.created_success'),
        ]);
        Inertia::flash('webhookToken', $this->issuedTokenPayload($webhook, $plainTextToken));

        return back();
    }

    public function update(UpdatePortalWebhookRequest $request, PortalWebhook $portalWebhook): RedirectResponse
    {
        $portalWebhook->update([
            'name' => $request->validated('name'),
            'permissions' => $request->permissions(),
            'is_active' => $request->boolean('is_active'),
            'expires_at' => $request->expiresAt(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.webhooks.updated_success'),
        ]);

        return back();
    }

    public function regenerate(Request $request, PortalWebhook $portalWebhook): RedirectResponse
    {
        $plainTextToken = $portalWebhook->issueToken();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.webhooks.regenerated_success'),
        ]);
        Inertia::flash('webhookToken', $this->issuedTokenPayload($portalWebhook, $plainTextToken));

        return back();
    }

    public function destroy(PortalWebhook $portalWebhook): RedirectResponse
    {
        $portalWebhook->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.webhooks.deleted_success'),
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWebhook(PortalWebhook $webhook): array
    {
        $creatorName = trim(($webhook->creator?->name ?? '').' '.($webhook->creator?->last_name ?? ''));

        return [
            'id' => $webhook->id,
            'name' => $webhook->name,
            'token_prefix' => $webhook->token_prefix,
            'permissions' => $webhook->resolvedPermissions(),
            'is_active' => $webhook->is_active,
            'is_expired' => $webhook->isExpired(),
            'expires_at' => $webhook->expires_at?->toISOString(),
            'last_used_at' => $webhook->last_used_at?->toISOString(),
            'created_at' => $webhook->created_at?->toISOString(),
            'endpoint_url' => $webhook->endpointUrl(),
            'creator' => $webhook->creator
                ? [
                    'id' => $webhook->creator->id,
                    'name' => $creatorName !== '' ? $creatorName : $webhook->creator->email,
                    'email' => $webhook->creator->email,
                ]
                : null,
        ];
    }

    /**
     * @return array{name: string, token: string, endpoint_url: string, signed_url: string}
     */
    private function issuedTokenPayload(PortalWebhook $webhook, string $plainTextToken): array
    {
        return [
            'name' => $webhook->name,
            'token' => $plainTextToken,
            'endpoint_url' => $webhook->endpointUrl(),
            'signed_url' => $webhook->signedUrl($plainTextToken),
        ];
    }
}
