<?php

use App\Models\ApiAccessToken;
use App\Models\GoodsReceipt;
use App\Models\PortalSetting;
use App\Models\PortalWebhook;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Models\WarehousePlace;
use App\Support\ApiCatalog;
use App\Support\ProcurementPageData;
use App\Support\WebhookDocumentationCatalog;
use Inertia\Testing\AssertableInertia as Assert;

function procurementSuperAdmin(): User
{
    config(['admin.super_admin_email' => 'procurement-admin@example.com']);

    return User::factory()->create([
        'email' => 'procurement-admin@example.com',
    ]);
}

/** @return array<string, mixed> */
function procurementSupplierPayload(string $name = 'ТОО ПромСнаб'): array
{
    return [
        'name' => $name,
        'bin' => '123456789012',
        'contact_person' => 'Айгуль Садыкова',
        'email' => 'supply@example.com',
        'phone' => '+7 700 000 00 00',
        'currency' => 'KZT',
        'payment_terms_days' => 15,
        'lead_time_days' => 5,
        'rating' => 4.8,
        'is_active' => true,
        'notes' => 'Основной поставщик',
    ];
}

/** @return array<string, mixed> */
function procurementRequestPayload(WarehousePlace $place): array
{
    return [
        'title' => 'Комплектующие для линии №3',
        'needed_at' => now()->addWeek()->toDateString(),
        'budget_amount' => 1500,
        'currency' => 'KZT',
        'justification' => 'Пополнение запаса для производственного заказа.',
        'items' => [
            [
                'item_name' => 'Подшипник SKF 6204',
                'sku' => 'SKF-6204',
                'unit' => 'шт.',
                'quantity' => 10,
                'target_unit_price' => 120,
                'warehouse_place_id' => $place->id,
                'production_reference' => 'MO-2026-003',
            ],
        ],
    ];
}

function issueProcurementApiToken(User $user, array $permissions): string
{
    $plainTextToken = ApiAccessToken::generatePlainTextToken();

    ApiAccessToken::query()->create([
        'user_id' => $user->id,
        'name' => 'Procurement API',
        ...ApiAccessToken::tokenAttributes($plainTextToken),
        'permissions' => $permissions,
    ]);

    return $plainTextToken;
}

test('procurement page, settings permissions, and built-in documentation are available', function () {
    $this->withoutVite();
    $superAdmin = procurementSuperAdmin();

    $this->actingAs($superAdmin)
        ->get(route('procurement.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('procurement/Index')
            ->where('summary.pending_approvals', 0)
            ->where('can.manage', true)
            ->where('can.approve_budget', true)
            ->where('can.manage_orders', true)
            ->where('can.receive_orders', true)
            ->where('can.return_goods', true)
        );

    expect(ApiAccessToken::permissionDefinitions())
        ->toHaveKeys([
            ApiAccessToken::PERMISSION_PROCUREMENT_READ,
            ApiAccessToken::PERMISSION_PROCUREMENT_WRITE,
        ])
        ->and(PortalWebhook::permissionDefinitions())
        ->toHaveKeys([
            PortalWebhook::PERMISSION_PROCUREMENT_READ,
            PortalWebhook::PERMISSION_PROCUREMENT_WRITE,
        ])
        ->and(app(WebhookDocumentationCatalog::class)->payload())
        ->toHaveKeys([
            'procurement_index_url',
            'procurement_suppliers_store_url',
            'procurement_requests_decision_url',
            'procurement_receipts_store_url',
            'procurement_returns_store_url',
        ]);

    $procurementApiSection = collect(app(ApiCatalog::class)->sections())
        ->firstWhere('title', __('ui.api.section_procurement'));

    expect($procurementApiSection)
        ->not->toBeNull()
        ->and($procurementApiSection['endpoints'])->toHaveCount(10);

    $this->actingAs($superAdmin)
        ->get(route('settings.webhooks.documentation.edit'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/WebhookDocumentation')
            ->where(
                'documentation.procurement_index_url',
                url('/portal-webhooks').'/{webhook_id}/procurement',
            )
            ->where(
                'documentation.procurement_returns_store_url',
                url('/portal-webhooks').'/{webhook_id}/procurement/returns',
            )
        );
});

test('full web procurement cycle updates warehouse stock transactionally', function () {
    $superAdmin = procurementSuperAdmin();
    $place = WarehousePlace::factory()->create();

    $this->actingAs($superAdmin)
        ->post(route('procurement.suppliers.store'), procurementSupplierPayload())
        ->assertRedirect();

    $supplier = Supplier::query()->sole();

    $this->actingAs($superAdmin)
        ->patch(
            route('procurement.suppliers.update', $supplier),
            [
                ...procurementSupplierPayload(),
                'rating' => 4.9,
                'lead_time_days' => 4,
            ],
        )
        ->assertRedirect();

    expect((float) $supplier->refresh()->rating)->toBe(4.9)
        ->and($supplier->lead_time_days)->toBe(4);

    $this->actingAs($superAdmin)
        ->post(route('procurement.requests.store'), procurementRequestPayload($place))
        ->assertRedirect();

    $purchaseRequest = PurchaseRequest::query()->with('items')->sole();

    expect($purchaseRequest->status)->toBe(PurchaseRequest::STATUS_PENDING_APPROVAL)
        ->and((float) $purchaseRequest->budget_amount)->toBe(1500.0)
        ->and($purchaseRequest->items)->toHaveCount(1);

    $this->actingAs($superAdmin)
        ->patch(route('procurement.requests.decision.update', $purchaseRequest), [
            'decision' => 'approve',
        ])
        ->assertRedirect();

    $purchaseRequest->refresh();
    expect($purchaseRequest->status)->toBe(PurchaseRequest::STATUS_APPROVED);

    $requestItem = $purchaseRequest->items()->sole();

    $this->actingAs($superAdmin)
        ->post(route('procurement.quotations.store'), [
            'purchase_request_item_id' => $requestItem->id,
            'supplier_id' => $supplier->id,
            'unit_price' => 100,
            'currency' => 'KZT',
            'tax_percent' => 12,
            'delivery_cost' => 20,
            'quoted_at' => now()->toDateString(),
            'valid_until' => now()->addMonth()->toDateString(),
            'lead_time_days' => 5,
        ])
        ->assertRedirect();

    $quotation = $requestItem->quotations()->sole();

    $this->actingAs($superAdmin)
        ->post(route('procurement.orders.store'), [
            'purchase_request_id' => $purchaseRequest->id,
            'supplier_id' => $supplier->id,
            'quotation_ids' => [$quotation->id],
            'ordered_at' => now()->toDateString(),
            'expected_at' => now()->addDays(5)->toDateString(),
        ])
        ->assertRedirect();

    $purchaseOrder = PurchaseOrder::query()->with('items')->sole();
    $orderItem = $purchaseOrder->items->sole();

    expect($purchaseOrder->status)->toBe(PurchaseOrder::STATUS_DRAFT)
        ->and((float) $purchaseOrder->total_amount)->toBe(1140.0)
        ->and($purchaseRequest->refresh()->status)->toBe(PurchaseRequest::STATUS_ORDERED);

    $this->actingAs($superAdmin)
        ->patch(route('procurement.orders.send', $purchaseOrder))
        ->assertRedirect();

    $this->actingAs($superAdmin)
        ->post(route('procurement.receipts.store'), [
            'purchase_order_id' => $purchaseOrder->id,
            'received_at' => now()->toDateString(),
            'external_reference' => 'INV-001',
            'items' => [
                [
                    'purchase_order_item_id' => $orderItem->id,
                    'warehouse_place_id' => $place->id,
                    'quantity' => 6,
                ],
            ],
        ])
        ->assertRedirect();

    $warehouseItem = WarehouseItem::query()->where('sku', 'SKF-6204')->sole();
    $firstReceipt = GoodsReceipt::query()->with('items')->sole();

    expect($warehouseItem->quantity)->toBe(6)
        ->and($purchaseOrder->refresh()->status)->toBe(PurchaseOrder::STATUS_PARTIALLY_RECEIVED)
        ->and($purchaseRequest->refresh()->status)->toBe(PurchaseRequest::STATUS_PARTIALLY_RECEIVED);

    $this->actingAs($superAdmin)
        ->post(route('procurement.receipts.store'), [
            'purchase_order_id' => $purchaseOrder->id,
            'received_at' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $orderItem->id,
                    'warehouse_place_id' => $place->id,
                    'quantity' => 4,
                ],
            ],
        ])
        ->assertRedirect();

    expect($warehouseItem->refresh()->quantity)->toBe(10)
        ->and($purchaseOrder->refresh()->status)->toBe(PurchaseOrder::STATUS_RECEIVED)
        ->and($purchaseRequest->refresh()->status)->toBe(PurchaseRequest::STATUS_RECEIVED);

    $this->actingAs($superAdmin)
        ->post(route('procurement.returns.store'), [
            'purchase_order_id' => $purchaseOrder->id,
            'returned_at' => now()->toDateString(),
            'reason' => 'Обнаружен производственный дефект.',
            'items' => [
                [
                    'goods_receipt_item_id' => $firstReceipt->items->sole()->id,
                    'quantity' => 3,
                ],
            ],
        ])
        ->assertRedirect();

    $purchaseReturn = PurchaseReturn::query()->with('items')->sole();

    expect($warehouseItem->refresh()->quantity)->toBe(7)
        ->and((float) $purchaseReturn->total_amount)->toBe(300.0)
        ->and($purchaseReturn->items->sole()->quantity)->toBe(3);

    $this->actingAs($superAdmin)
        ->post(route('procurement.returns.store'), [
            'purchase_order_id' => $purchaseOrder->id,
            'returned_at' => now()->toDateString(),
            'reason' => 'Повторный возврат.',
            'items' => [
                [
                    'goods_receipt_item_id' => $firstReceipt->items->sole()->id,
                    'quantity' => 4,
                ],
            ],
        ])
        ->assertSessionHasErrors('items.0.quantity');

    expect($warehouseItem->refresh()->quantity)->toBe(7);

    $summary = app(ProcurementPageData::class)->index($superAdmin)['summary'];

    expect($summary['month_receipts'])->toBe(['KZT' => 1000.0])
        ->and($summary['potential_savings'])->toBe(['KZT' => 0.0]);
});

test('procurement api uses separate read and write token permissions', function () {
    $superAdmin = procurementSuperAdmin();
    $readToken = issueProcurementApiToken($superAdmin, [
        ApiAccessToken::PERMISSION_PROCUREMENT_READ,
    ]);
    $writeToken = issueProcurementApiToken($superAdmin, [
        ApiAccessToken::PERMISSION_PROCUREMENT_READ,
        ApiAccessToken::PERMISSION_PROCUREMENT_WRITE,
    ]);

    $this->withToken($readToken)
        ->getJson(route('api.v1.procurement.index'))
        ->assertOk()
        ->assertJsonStructure(['data' => ['summary', 'suppliers', 'purchaseRequests', 'purchaseOrders']]);

    $this->withToken($readToken)
        ->postJson(route('api.v1.procurement.suppliers.store'), procurementSupplierPayload('ТОО Read Only'))
        ->assertForbidden();

    $this->withToken($writeToken)
        ->postJson(route('api.v1.procurement.suppliers.store'), procurementSupplierPayload('ТОО API Снаб'))
        ->assertCreated()
        ->assertJsonPath('data.name', 'ТОО API Снаб');
});

test('procurement webhook exposes read and write endpoints and respects its permissions', function () {
    $creator = procurementSuperAdmin();
    $webhook = PortalWebhook::factory()->create([
        'created_by_user_id' => $creator->id,
        'permissions' => [
            PortalWebhook::PERMISSION_PROCUREMENT_READ,
            PortalWebhook::PERMISSION_PROCUREMENT_WRITE,
        ],
    ]);
    $webhook->issueToken('procurement-webhook-token');

    $this->get(route('portal-webhooks.invoke', $webhook).'?token=procurement-webhook-token')
        ->assertOk()
        ->assertJsonPath(
            'endpoints.procurement.index',
            route('portal-webhooks.procurement.index', $webhook).'?token=procurement-webhook-token',
        )
        ->assertJsonPath(
            'endpoints.procurement_write.receipts_store',
            route('portal-webhooks.procurement.receipts.store', $webhook).'?token=procurement-webhook-token',
        );

    $this->postJson(
        route('portal-webhooks.procurement.suppliers.store', $webhook).'?token=procurement-webhook-token',
        procurementSupplierPayload('ТОО Webhook Снаб'),
    )
        ->assertCreated()
        ->assertJsonPath('webhook.id', $webhook->id)
        ->assertJsonPath('data.name', 'ТОО Webhook Снаб');

    $readOnlyWebhook = PortalWebhook::factory()->create([
        'created_by_user_id' => $creator->id,
        'permissions' => [PortalWebhook::PERMISSION_PROCUREMENT_READ],
    ]);
    $readOnlyWebhook->issueToken('procurement-read-token');

    $this->postJson(
        route('portal-webhooks.procurement.suppliers.store', $readOnlyWebhook).'?token=procurement-read-token',
        procurementSupplierPayload('ТОО Forbidden'),
    )->assertForbidden();
});

test('disabling procurement hides its menu and blocks web api and webhook routes', function () {
    $superAdmin = procurementSuperAdmin();
    PortalSetting::current()->update([
        'disabled_modules' => ['procurement'],
    ]);

    $this->actingAs($superAdmin)
        ->get(route('procurement.index'))
        ->assertNotFound();

    $dashboard = $this->actingAs($superAdmin)->get(route('dashboard'));

    expect($dashboard->inertiaProps('menu.hiddenItems'))->toContain('procurement')
        ->and($dashboard->inertiaProps('auth.canAccessProcurement'))->toBeFalse();

    $token = issueProcurementApiToken($superAdmin, [
        ApiAccessToken::PERMISSION_PROCUREMENT_READ,
    ]);

    $this->withToken($token)
        ->getJson(route('api.v1.procurement.index'))
        ->assertNotFound();

    $webhook = PortalWebhook::factory()->create([
        'created_by_user_id' => $superAdmin->id,
        'permissions' => [PortalWebhook::PERMISSION_PROCUREMENT_READ],
    ]);
    $webhook->issueToken('disabled-procurement-token');

    $this->withHeaders(['Authorization' => ''])->get(
        route('portal-webhooks.procurement.index', $webhook).'?token=disabled-procurement-token',
    )->assertNotFound();
});
