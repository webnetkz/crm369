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
            'calendar_events_url' => url('/portal-webhooks').'/{webhook_id}/calendar/events',
            'contacts_index_url' => url('/portal-webhooks').'/{webhook_id}/contacts',
            'contacts_show_url' => url('/portal-webhooks').'/{webhook_id}/contacts/{contact_id}',
            'contacts_store_url' => url('/portal-webhooks').'/{webhook_id}/contacts',
            'contacts_update_url' => url('/portal-webhooks').'/{webhook_id}/contacts/{contact_id}',
            'contacts_destroy_url' => url('/portal-webhooks').'/{webhook_id}/contacts/{contact_id}',
            'directories_index_url' => url('/portal-webhooks').'/{webhook_id}/directories',
            'directories_show_url' => url('/portal-webhooks').'/{webhook_id}/directories/{directory_id}',
            'directories_export_url' => url('/portal-webhooks').'/{webhook_id}/directories/{directory_id}/export',
            'directories_template_url' => url('/portal-webhooks').'/{webhook_id}/directories/{directory_id}/template',
            'directories_store_url' => url('/portal-webhooks').'/{webhook_id}/directories',
            'directories_update_url' => url('/portal-webhooks').'/{webhook_id}/directories/{directory_id}',
            'directories_destroy_url' => url('/portal-webhooks').'/{webhook_id}/directories/{directory_id}',
            'directories_import_url' => url('/portal-webhooks').'/{webhook_id}/directories/{directory_id}/import',
            'directory_records_store_url' => url('/portal-webhooks').'/{webhook_id}/directories/{directory_id}/records',
            'directory_records_update_url' => url('/portal-webhooks').'/{webhook_id}/directories/{directory_id}/records/{record_id}',
            'directory_records_destroy_url' => url('/portal-webhooks').'/{webhook_id}/directories/{directory_id}/records/{record_id}',
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
            'procurement_index_url' => url('/portal-webhooks').'/{webhook_id}/procurement',
            'procurement_suppliers_store_url' => url('/portal-webhooks').'/{webhook_id}/procurement/suppliers',
            'procurement_suppliers_update_url' => url('/portal-webhooks').'/{webhook_id}/procurement/suppliers/{supplier_id}',
            'procurement_requests_store_url' => url('/portal-webhooks').'/{webhook_id}/procurement/requests',
            'procurement_requests_decision_url' => url('/portal-webhooks').'/{webhook_id}/procurement/requests/{purchase_request_id}/decision',
            'procurement_quotations_store_url' => url('/portal-webhooks').'/{webhook_id}/procurement/quotations',
            'procurement_orders_store_url' => url('/portal-webhooks').'/{webhook_id}/procurement/orders',
            'procurement_orders_send_url' => url('/portal-webhooks').'/{webhook_id}/procurement/orders/{purchase_order_id}/send',
            'procurement_receipts_store_url' => url('/portal-webhooks').'/{webhook_id}/procurement/receipts',
            'procurement_returns_store_url' => url('/portal-webhooks').'/{webhook_id}/procurement/returns',
        ];
    }
}
