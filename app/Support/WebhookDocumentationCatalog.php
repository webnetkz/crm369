<?php

namespace App\Support;

use function url;

class WebhookDocumentationCatalog
{
    /**
     * @return array<string, string>
     */
    public function payload(): array
    {
        return [
            'base_url' => url('/portal-webhooks').'/{webhook_id}',
            'users_index_url' => url('/portal-webhooks').'/{webhook_id}/users',
            'users_show_url' => url('/portal-webhooks').'/{webhook_id}/users/{user_id}',
            'company_structure_index_url' => url('/portal-webhooks').'/{webhook_id}/company-structure',
            'company_structure_show_url' => url('/portal-webhooks').'/{webhook_id}/company-structure/users/{user_id}',
            'contacts_index_url' => url('/portal-webhooks').'/{webhook_id}/contacts',
            'contacts_show_url' => url('/portal-webhooks').'/{webhook_id}/contacts/{contact_id}',
            'contacts_store_url' => url('/portal-webhooks').'/{webhook_id}/contacts',
            'contacts_update_url' => url('/portal-webhooks').'/{webhook_id}/contacts/{contact_id}',
            'contacts_destroy_url' => url('/portal-webhooks').'/{webhook_id}/contacts/{contact_id}',
            'equipment_index_url' => url('/portal-webhooks').'/{webhook_id}/equipment',
            'equipment_show_url' => url('/portal-webhooks').'/{webhook_id}/equipment/{equipment_id}',
            'equipment_store_url' => url('/portal-webhooks').'/{webhook_id}/equipment',
            'equipment_update_url' => url('/portal-webhooks').'/{webhook_id}/equipment/{equipment_id}',
            'edo_index_url' => url('/portal-webhooks').'/{webhook_id}/edo/documents',
            'edo_show_url' => url('/portal-webhooks').'/{webhook_id}/edo/documents/{edo_document_id}',
            'edo_store_url' => url('/portal-webhooks').'/{webhook_id}/edo/documents',
            'edo_update_url' => url('/portal-webhooks').'/{webhook_id}/edo/documents/{edo_document_id}',
            'edo_public_link_url' => url('/portal-webhooks').'/{webhook_id}/edo/documents/{edo_document_id}/public-link',
            'tsd_index_url' => url('/portal-webhooks').'/{webhook_id}/tsd/scans',
            'tsd_store_url' => url('/portal-webhooks').'/{webhook_id}/tsd/scans',
            'warehouses_index_url' => url('/portal-webhooks').'/{webhook_id}/warehouses',
            'warehouses_show_url' => url('/portal-webhooks').'/{webhook_id}/warehouses/{warehouse_id}',
            'warehouses_items_url' => url('/portal-webhooks').'/{webhook_id}/warehouses/{warehouse_id}/items',
            'warehouses_store_url' => url('/portal-webhooks').'/{webhook_id}/warehouses',
            'warehouses_update_url' => url('/portal-webhooks').'/{webhook_id}/warehouses/{warehouse_id}',
            'warehouses_destroy_url' => url('/portal-webhooks').'/{webhook_id}/warehouses/{warehouse_id}',
        ];
    }
}
