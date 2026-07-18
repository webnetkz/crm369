<?php

namespace App\Http\Controllers;

use App\Concerns\EnsuresContactsTableIsReady;
use App\Http\Resources\ApiContactResource;
use App\Http\Resources\ApiEdoDocumentResource;
use App\Http\Resources\ApiEquipmentResource;
use App\Http\Resources\ApiReferenceDirectoryResource;
use App\Http\Resources\ApiUserResource;
use App\Http\Resources\ApiWarehouseResource;
use App\Models\Contact;
use App\Models\EdoDocument;
use App\Models\EquipmentItem;
use App\Models\PortalWebhook;
use App\Models\ReferenceDirectory;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\CompanyStructureData;
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
            'company_structure' => $this->companyStructurePayload($resolvedWebhook),
            'contacts' => $this->contactsPayload($resolvedWebhook),
            'directories' => $this->directoriesPayload($resolvedWebhook),
            'edo_documents' => $this->edoPayload($resolvedWebhook),
            'equipment_items' => $this->equipmentPayload($resolvedWebhook),
            'warehouses' => $this->warehousesPayload($resolvedWebhook),
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

        if ($portalWebhook->hasPermission(PortalWebhook::PERMISSION_COMPANY_STRUCTURE_READ)) {
            $endpoints['company_structure'] = [
                'index' => route('portal-webhooks.company-structure.index', $portalWebhook).'?token='.urlencode($plainTextToken),
                'show_template' => route('portal-webhooks.company-structure.show', [
                    'portalWebhook' => $portalWebhook,
                    'user' => '__USER_ID__',
                ]).'?token='.urlencode($plainTextToken),
            ];
        }

        if ($portalWebhook->hasPermission(PortalWebhook::PERMISSION_CALENDAR_READ)) {
            $endpoints['calendar'] = [
                'events' => route('portal-webhooks.calendar.events.index', $portalWebhook).'?token='.urlencode($plainTextToken),
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

        if ($portalWebhook->hasPermission(PortalWebhook::PERMISSION_DIRECTORIES_READ)) {
            $endpoints['directories'] = [
                'index' => route('portal-webhooks.directories.index', $portalWebhook).'?token='.urlencode($plainTextToken),
                'show_template' => route('portal-webhooks.directories.show', [
                    'portalWebhook' => $portalWebhook,
                    'referenceDirectory' => '__DIRECTORY_ID__',
                ]).'?token='.urlencode($plainTextToken),
                'export_template' => route('portal-webhooks.directories.export', [
                    'portalWebhook' => $portalWebhook,
                    'referenceDirectory' => '__DIRECTORY_ID__',
                ]).'?token='.urlencode($plainTextToken),
                'download_template_template' => route('portal-webhooks.directories.template', [
                    'portalWebhook' => $portalWebhook,
                    'referenceDirectory' => '__DIRECTORY_ID__',
                ]).'?token='.urlencode($plainTextToken),
            ];
        }

        if ($portalWebhook->hasPermission(PortalWebhook::PERMISSION_DIRECTORIES_WRITE)) {
            $endpoints['directories_write'] = [
                'store' => route('portal-webhooks.directories.store', $portalWebhook).'?token='.urlencode($plainTextToken),
                'update_template' => route('portal-webhooks.directories.update', [
                    'portalWebhook' => $portalWebhook,
                    'referenceDirectory' => '__DIRECTORY_ID__',
                ]).'?token='.urlencode($plainTextToken),
                'destroy_template' => route('portal-webhooks.directories.destroy', [
                    'portalWebhook' => $portalWebhook,
                    'referenceDirectory' => '__DIRECTORY_ID__',
                ]).'?token='.urlencode($plainTextToken),
                'import_template' => route('portal-webhooks.directories.import', [
                    'portalWebhook' => $portalWebhook,
                    'referenceDirectory' => '__DIRECTORY_ID__',
                ]).'?token='.urlencode($plainTextToken),
                'record_store_template' => route('portal-webhooks.directories.records.store', [
                    'portalWebhook' => $portalWebhook,
                    'referenceDirectory' => '__DIRECTORY_ID__',
                ]).'?token='.urlencode($plainTextToken),
                'record_update_template' => route('portal-webhooks.directories.records.update', [
                    'portalWebhook' => $portalWebhook,
                    'referenceDirectory' => '__DIRECTORY_ID__',
                    'referenceDirectoryRecord' => '__RECORD_ID__',
                ]).'?token='.urlencode($plainTextToken),
                'record_destroy_template' => route('portal-webhooks.directories.records.destroy', [
                    'portalWebhook' => $portalWebhook,
                    'referenceDirectory' => '__DIRECTORY_ID__',
                    'referenceDirectoryRecord' => '__RECORD_ID__',
                ]).'?token='.urlencode($plainTextToken),
            ];
        }

        if ($portalWebhook->hasPermission(PortalWebhook::PERMISSION_EDO_READ)) {
            $endpoints['edo'] = [
                'index' => route('portal-webhooks.edo.index', $portalWebhook).'?token='.urlencode($plainTextToken),
                'show_template' => route('portal-webhooks.edo.show', [
                    'portalWebhook' => $portalWebhook,
                    'edoDocument' => '__EDO_DOCUMENT_ID__',
                ]).'?token='.urlencode($plainTextToken),
            ];
        }

        if ($portalWebhook->hasPermission(PortalWebhook::PERMISSION_EDO_WRITE)) {
            $endpoints['edo_write'] = [
                'store' => route('portal-webhooks.edo.store', $portalWebhook).'?token='.urlencode($plainTextToken),
                'update_template' => route('portal-webhooks.edo.update', [
                    'portalWebhook' => $portalWebhook,
                    'edoDocument' => '__EDO_DOCUMENT_ID__',
                ]).'?token='.urlencode($plainTextToken),
                'public_link_template' => route('portal-webhooks.edo.public-link.store', [
                    'portalWebhook' => $portalWebhook,
                    'edoDocument' => '__EDO_DOCUMENT_ID__',
                ]).'?token='.urlencode($plainTextToken),
            ];
        }

        if ($portalWebhook->hasPermission(PortalWebhook::PERMISSION_EQUIPMENT_READ)) {
            $endpoints['equipment'] = [
                'index' => route('portal-webhooks.equipment.index', $portalWebhook).'?token='.urlencode($plainTextToken),
                'show_template' => route('portal-webhooks.equipment.show', [
                    'portalWebhook' => $portalWebhook,
                    'equipmentItem' => '__EQUIPMENT_ID__',
                ]).'?token='.urlencode($plainTextToken),
            ];
        }

        if ($portalWebhook->hasPermission(PortalWebhook::PERMISSION_EQUIPMENT_WRITE)) {
            $endpoints['equipment_write'] = [
                'store' => route('portal-webhooks.equipment.store', $portalWebhook).'?token='.urlencode($plainTextToken),
                'update_template' => route('portal-webhooks.equipment.update', [
                    'portalWebhook' => $portalWebhook,
                    'equipmentItem' => '__EQUIPMENT_ID__',
                ]).'?token='.urlencode($plainTextToken),
            ];
        }

        if ($portalWebhook->hasPermission(PortalWebhook::PERMISSION_TSD_READ)) {
            $endpoints['tsd'] = [
                'index' => route('portal-webhooks.tsd.index', $portalWebhook).'?token='.urlencode($plainTextToken),
            ];
        }

        if ($portalWebhook->hasPermission(PortalWebhook::PERMISSION_TSD_WRITE)) {
            $endpoints['tsd_write'] = [
                'store' => route('portal-webhooks.tsd.store', $portalWebhook).'?token='.urlencode($plainTextToken),
            ];
        }

        if ($portalWebhook->hasPermission(PortalWebhook::PERMISSION_WAREHOUSES_READ)) {
            $endpoints['warehouses'] = [
                'index' => route('portal-webhooks.warehouses.index', $portalWebhook).'?token='.urlencode($plainTextToken),
                'show_template' => route('portal-webhooks.warehouses.show', [
                    'portalWebhook' => $portalWebhook,
                    'warehouse' => '__WAREHOUSE_ID__',
                ]).'?token='.urlencode($plainTextToken),
                'items_template' => route('portal-webhooks.warehouses.items', [
                    'portalWebhook' => $portalWebhook,
                    'warehouse' => '__WAREHOUSE_ID__',
                ]).'?token='.urlencode($plainTextToken),
            ];
        }

        if ($portalWebhook->hasPermission(PortalWebhook::PERMISSION_WAREHOUSES_WRITE)) {
            $endpoints['warehouses_write'] = [
                'store' => route('portal-webhooks.warehouses.store', $portalWebhook).'?token='.urlencode($plainTextToken),
                'update_template' => route('portal-webhooks.warehouses.update', [
                    'portalWebhook' => $portalWebhook,
                    'warehouse' => '__WAREHOUSE_ID__',
                ]).'?token='.urlencode($plainTextToken),
                'destroy_template' => route('portal-webhooks.warehouses.destroy', [
                    'portalWebhook' => $portalWebhook,
                    'warehouse' => '__WAREHOUSE_ID__',
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
     * @return array<string, mixed>|null
     */
    private function companyStructurePayload(PortalWebhook $portalWebhook): ?array
    {
        if (! $portalWebhook->hasPermission(PortalWebhook::PERMISSION_COMPANY_STRUCTURE_READ)) {
            return null;
        }

        return app(CompanyStructureData::class)->webhookData();
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

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function directoriesPayload(PortalWebhook $portalWebhook): ?array
    {
        if (! $portalWebhook->hasPermission(PortalWebhook::PERMISSION_DIRECTORIES_READ)) {
            return null;
        }

        return ReferenceDirectory::query()
            ->withCount('records')
            ->with([
                'creator:id,name,last_name,email',
                'updater:id,name,last_name,email',
                'records.creator:id,name,last_name,email',
                'records.updater:id,name,last_name,email',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (ReferenceDirectory $directory): array => (new ApiReferenceDirectoryResource($directory))->resolve())
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function edoPayload(PortalWebhook $portalWebhook): ?array
    {
        if (! $portalWebhook->hasPermission(PortalWebhook::PERMISSION_EDO_READ)) {
            return null;
        }

        return EdoDocument::query()
            ->with([
                'creator:id,name,last_name,email,user_group_id',
                'updater:id,name,last_name,email,user_group_id',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (EdoDocument $document): array => (new ApiEdoDocumentResource($document))->resolve())
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function equipmentPayload(PortalWebhook $portalWebhook): ?array
    {
        if (! $portalWebhook->hasPermission(PortalWebhook::PERMISSION_EQUIPMENT_READ)) {
            return null;
        }

        return EquipmentItem::query()
            ->with([
                'issuedToUser:id,name,last_name,email',
                'responsibleUser:id,name,last_name,email',
                'createdByUser:id,name,last_name,email',
                'updatedByUser:id,name,last_name,email',
            ])
            ->ordered()
            ->get()
            ->map(fn (EquipmentItem $equipmentItem): array => (new ApiEquipmentResource($equipmentItem))->resolve())
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function warehousesPayload(PortalWebhook $portalWebhook): ?array
    {
        if (! $portalWebhook->hasPermission(PortalWebhook::PERMISSION_WAREHOUSES_READ)) {
            return null;
        }

        return Warehouse::query()
            ->with([
                'creator:id,name,last_name',
                'updater:id,name,last_name',
                'rows.columns.floors.places',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Warehouse $warehouse): array => (new ApiWarehouseResource($warehouse))->resolve())
            ->values()
            ->all();
    }
}
