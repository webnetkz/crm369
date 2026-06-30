<?php

namespace App\Http\Controllers;

use App\Concerns\EnsuresContactsTableIsReady;
use App\Http\Resources\ApiContactResource;
use App\Http\Resources\ApiUserResource;
use App\Models\Contact;
use App\Models\PortalWebhook;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalWebhookInvokeController extends Controller
{
    use EnsuresContactsTableIsReady;

    public function __invoke(Request $request, PortalWebhook $portalWebhook): JsonResponse
    {
        /** @var PortalWebhook $resolvedWebhook */
        $resolvedWebhook = $request->attributes->get('portal_webhook', $portalWebhook);
        $plainTextToken = trim((string) $request->attributes->get('portal_webhook_token', ''));

        return response()->json([
            'id' => $resolvedWebhook->id,
            'name' => $resolvedWebhook->name,
            'permissions' => $resolvedWebhook->resolvedPermissions(),
            'expires_at' => $resolvedWebhook->expires_at?->toISOString(),
            'last_used_at' => $resolvedWebhook->last_used_at?->toISOString(),
            'users' => $this->usersPayload($resolvedWebhook),
            'contacts' => $this->contactsPayload($resolvedWebhook),
            'endpoints' => $this->availableEndpoints($resolvedWebhook, $plainTextToken),
        ]);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function availableEndpoints(PortalWebhook $portalWebhook, string $plainTextToken): array
    {
        $endpoints = [];

        if ($portalWebhook->hasPermission(PortalWebhook::PERMISSION_USERS_READ)) {
            $endpoints['users'] = [
                'index' => route('portal-webhooks.users.index', $portalWebhook).'?token='.urlencode($plainTextToken),
                'show_template' => route('portal-webhooks.users.show', [
                    'portalWebhook' => $portalWebhook,
                    'user' => '__USER_ID__',
                ]).'?token='.urlencode($plainTextToken),
            ];
        }

        if ($portalWebhook->hasPermission(PortalWebhook::PERMISSION_CONTACTS_READ) && $this->contactsTableExists()) {
            $endpoints['contacts'] = [
                'index' => route('portal-webhooks.contacts.index', $portalWebhook).'?token='.urlencode($plainTextToken),
                'show_template' => route('portal-webhooks.contacts.show', [
                    'portalWebhook' => $portalWebhook,
                    'contact' => '__CONTACT_ID__',
                ]).'?token='.urlencode($plainTextToken),
            ];
        }

        if ($portalWebhook->hasPermission(PortalWebhook::PERMISSION_CONTACTS_WRITE) && $this->contactsTableExists()) {
            $endpoints['contacts_write'] = [
                'store' => route('portal-webhooks.contacts.store', $portalWebhook).'?token='.urlencode($plainTextToken),
                'update_template' => route('portal-webhooks.contacts.update', [
                    'portalWebhook' => $portalWebhook,
                    'contact' => '__CONTACT_ID__',
                ]).'?token='.urlencode($plainTextToken),
                'destroy_template' => route('portal-webhooks.contacts.destroy', [
                    'portalWebhook' => $portalWebhook,
                    'contact' => '__CONTACT_ID__',
                ]).'?token='.urlencode($plainTextToken),
            ];
        }

        return $endpoints;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function usersPayload(PortalWebhook $portalWebhook): ?array
    {
        if (! $portalWebhook->hasPermission(PortalWebhook::PERMISSION_USERS_READ)) {
            return null;
        }

        return User::query()
            ->with('group:id,name')
            ->select([
                'id',
                'name',
                'last_name',
                'email',
                'phone',
                'email_verified_at',
                'avatar_path',
                'avatar_scale',
                'avatar_position_x',
                'avatar_position_y',
                'language',
                'has_selected_language',
                'background_color',
                'background_image_path',
                'background_blur',
                'user_group_id',
                'is_active',
                'deactivated_at',
                'created_at',
                'updated_at',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => (new ApiUserResource($user))->resolve())
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function contactsPayload(PortalWebhook $portalWebhook): ?array
    {
        if (! $portalWebhook->hasPermission(PortalWebhook::PERMISSION_CONTACTS_READ)) {
            return null;
        }

        if (! $this->contactsTableExists()) {
            return [];
        }

        return Contact::query()
            ->with([
                'creator:id,name,last_name',
                'updater:id,name,last_name',
            ])
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (Contact $contact): array => (new ApiContactResource($contact))->resolve())
            ->values()
            ->all();
    }
}
