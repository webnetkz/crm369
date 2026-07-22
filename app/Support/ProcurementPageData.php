<?php

namespace App\Support;

use App\Models\Contact;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierQuotation;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Models\WarehousePlace;
use Illuminate\Support\Collection;

class ProcurementPageData
{
    /** @return array<string, mixed> */
    public function index(?User $user): array
    {
        $suppliers = Supplier::query()
            ->withCount(['quotations', 'purchaseOrders'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit(100)
            ->get();
        $purchaseRequests = PurchaseRequest::query()
            ->with([
                'requester:id,name,last_name',
                'approver:id,name,last_name',
                'items.warehousePlace.floor.column.row.warehouse:id,name',
                'items.quotations.supplier:id,name,rating,is_active',
                'items.quotations.purchaseRequestItem:id,quantity',
                'purchaseOrders:id,purchase_request_id,number,status,total_amount',
            ])
            ->latest()
            ->limit(50)
            ->get();
        $purchaseOrders = PurchaseOrder::query()
            ->with([
                'supplier:id,name,rating',
                'purchaseRequest:id,number,title,budget_amount',
                'items.warehousePlace.floor.column.row.warehouse:id,name',
                'items.warehouseItem:id,name,sku,quantity,warehouse_place_id',
            ])
            ->withCount(['goodsReceipts', 'purchaseReturns'])
            ->latest()
            ->limit(50)
            ->get();
        $goodsReceipts = GoodsReceipt::query()
            ->with([
                'receiver:id,name,last_name',
                'purchaseOrder.supplier:id,name',
                'items.purchaseOrderItem:id,item_name,sku,unit',
                'items.warehouseItem:id,name,sku,quantity',
                'items.purchaseReturnItems:id,goods_receipt_item_id,quantity',
            ])
            ->latest('received_at')
            ->limit(50)
            ->get();
        $purchaseReturns = PurchaseReturn::query()
            ->with([
                'creator:id,name,last_name',
                'purchaseOrder.supplier:id,name',
                'items.purchaseOrderItem:id,item_name,sku,unit',
                'items.warehouseItem:id,name,sku,quantity',
            ])
            ->latest('returned_at')
            ->limit(50)
            ->get();

        return [
            'summary' => $this->summary($purchaseRequests, $purchaseOrders),
            'suppliers' => $suppliers->map(fn (Supplier $supplier): array => $this->supplier($supplier))->all(),
            'purchaseRequests' => $purchaseRequests->map(fn (PurchaseRequest $request): array => $this->purchaseRequest($request))->all(),
            'purchaseOrders' => $purchaseOrders->map(fn (PurchaseOrder $order): array => $this->purchaseOrder($order))->all(),
            'goodsReceipts' => $goodsReceipts->map(fn (GoodsReceipt $receipt): array => $this->goodsReceipt($receipt))->all(),
            'purchaseReturns' => $purchaseReturns->map(fn (PurchaseReturn $return): array => $this->purchaseReturn($return))->all(),
            'warehousePlaces' => WarehousePlace::query()
                ->with('floor.column.row.warehouse:id,name')
                ->orderBy('id')
                ->get()
                ->map(fn (WarehousePlace $place): array => [
                    'id' => $place->id,
                    'name' => $place->name,
                    'path' => $this->placePath($place),
                ])
                ->all(),
            'warehouseItems' => WarehouseItem::query()
                ->select(['id', 'warehouse_place_id', 'name', 'sku', 'quantity'])
                ->orderBy('name')
                ->limit(500)
                ->get()
                ->map(fn (WarehouseItem $item): array => [
                    'id' => $item->id,
                    'warehouse_place_id' => $item->warehouse_place_id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity,
                ])
                ->all(),
            'companyContacts' => Contact::query()
                ->where('type', Contact::TYPE_COMPANY)
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name', 'contact_person', 'email', 'phone'])
                ->map(fn (Contact $contact): array => [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'contact_person' => $contact->contact_person,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                ])
                ->all(),
            'can' => [
                'manage' => $user?->canManageProcurement() ?? false,
                'approve_budget' => $user?->canApproveProcurementBudget() ?? false,
                'manage_orders' => $user?->canManageProcurementOrders() ?? false,
                'receive_orders' => $user?->canReceiveProcurementOrders() ?? false,
                'return_goods' => $user?->canReturnProcurementGoods() ?? false,
            ],
        ];
    }

    /**
     * @param  Collection<int, PurchaseRequest>  $purchaseRequests
     * @param  Collection<int, PurchaseOrder>  $purchaseOrders
     * @return array<string, int|array<string, float>>
     */
    private function summary(Collection $purchaseRequests, Collection $purchaseOrders): array
    {
        $potentialSavings = $purchaseRequests
            ->groupBy('currency')
            ->map(function (Collection $requests): float {
                return round((float) $requests->sum(function (PurchaseRequest $request): float {
                    return (float) $request->items->sum(function (PurchaseRequestItem $item) use ($request): float {
                        $prices = $item->quotations
                            ->filter(fn (SupplierQuotation $quotation): bool => $quotation->currency === $request->currency)
                            ->map(fn (SupplierQuotation $quotation): float => $quotation->landedUnitPrice());

                        return $prices->count() > 1
                            ? ((float) $prices->max() - (float) $prices->min()) * $item->quantity
                            : 0.0;
                    });
                }), 2);
            })
            ->all();
        $monthReceipts = GoodsReceiptItem::query()
            ->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'goods_receipts.purchase_order_id')
            ->whereBetween('goods_receipts.received_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('purchase_orders.currency, SUM(goods_receipt_items.line_total) AS aggregate')
            ->groupBy('purchase_orders.currency')
            ->pluck('aggregate', 'purchase_orders.currency')
            ->map(fn (mixed $amount): float => round((float) $amount, 2))
            ->all();

        return [
            'pending_approvals' => $purchaseRequests->where('status', PurchaseRequest::STATUS_PENDING_APPROVAL)->count(),
            'active_orders' => $purchaseOrders
                ->whereIn('status', [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_SENT, PurchaseOrder::STATUS_PARTIALLY_RECEIVED])
                ->count(),
            'month_receipts' => $monthReceipts,
            'potential_savings' => $potentialSavings,
        ];
    }

    /** @return array<string, mixed> */
    private function supplier(Supplier $supplier): array
    {
        return [
            'id' => $supplier->id,
            'contact_id' => $supplier->contact_id,
            'name' => $supplier->name,
            'bin' => $supplier->bin,
            'contact_person' => $supplier->contact_person,
            'email' => $supplier->email,
            'phone' => $supplier->phone,
            'currency' => $supplier->currency,
            'payment_terms_days' => $supplier->payment_terms_days,
            'lead_time_days' => $supplier->lead_time_days,
            'rating' => $supplier->rating,
            'is_active' => $supplier->is_active,
            'notes' => $supplier->notes,
            'quotation_count' => (int) $supplier->quotations_count,
            'order_count' => (int) $supplier->purchase_orders_count,
        ];
    }

    /** @return array<string, mixed> */
    private function purchaseRequest(PurchaseRequest $request): array
    {
        return [
            'id' => $request->id,
            'number' => $request->number,
            'title' => $request->title,
            'status' => $request->status,
            'needed_at' => $request->needed_at?->toDateString(),
            'budget_amount' => (float) $request->budget_amount,
            'estimated_total' => $request->estimatedTotal(),
            'currency' => $request->currency,
            'justification' => $request->justification,
            'rejection_reason' => $request->rejection_reason,
            'requested_by' => $this->userName($request->requester),
            'approved_by' => $this->userName($request->approver),
            'submitted_at' => $request->submitted_at?->toISOString(),
            'approved_at' => $request->approved_at?->toISOString(),
            'order' => $request->purchaseOrders->first()
                ? [
                    'id' => $request->purchaseOrders->first()->id,
                    'number' => $request->purchaseOrders->first()->number,
                    'status' => $request->purchaseOrders->first()->status,
                    'total_amount' => (float) $request->purchaseOrders->first()->total_amount,
                ]
                : null,
            'items' => $request->items->map(fn (PurchaseRequestItem $item): array => $this->purchaseRequestItem($item))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function purchaseRequestItem(PurchaseRequestItem $item): array
    {
        $quotes = $item->quotations
            ->sortBy(fn (SupplierQuotation $quotation): float => $quotation->landedUnitPrice())
            ->values();

        return [
            'id' => $item->id,
            'item_name' => $item->item_name,
            'sku' => $item->sku,
            'unit' => $item->unit,
            'quantity' => $item->quantity,
            'target_unit_price' => (float) $item->target_unit_price,
            'production_reference' => $item->production_reference,
            'warehouse_place_id' => $item->warehouse_place_id,
            'warehouse_item_id' => $item->warehouse_item_id,
            'warehouse_place' => $item->warehousePlace ? $this->placePath($item->warehousePlace) : null,
            'notes' => $item->notes,
            'best_quotation_id' => $quotes->first()?->id,
            'quotations' => $quotes->map(fn (SupplierQuotation $quotation): array => [
                'id' => $quotation->id,
                'supplier_id' => $quotation->supplier_id,
                'supplier_name' => $quotation->supplier->name,
                'supplier_rating' => $quotation->supplier->rating,
                'unit_price' => (float) $quotation->unit_price,
                'tax_percent' => (float) $quotation->tax_percent,
                'delivery_cost' => (float) $quotation->delivery_cost,
                'landed_unit_price' => $quotation->landedUnitPrice(),
                'landed_total' => $quotation->landedTotal(),
                'currency' => $quotation->currency,
                'quoted_at' => $quotation->quoted_at->toDateString(),
                'valid_until' => $quotation->valid_until?->toDateString(),
                'lead_time_days' => $quotation->lead_time_days,
                'notes' => $quotation->notes,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function purchaseOrder(PurchaseOrder $order): array
    {
        return [
            'id' => $order->id,
            'number' => $order->number,
            'purchase_request_id' => $order->purchase_request_id,
            'request_number' => $order->purchaseRequest?->number,
            'request_title' => $order->purchaseRequest?->title,
            'supplier_id' => $order->supplier_id,
            'supplier_name' => $order->supplier->name,
            'status' => $order->status,
            'currency' => $order->currency,
            'ordered_at' => $order->ordered_at->toDateString(),
            'expected_at' => $order->expected_at?->toDateString(),
            'sent_at' => $order->sent_at?->toISOString(),
            'subtotal' => (float) $order->subtotal,
            'tax_amount' => (float) $order->tax_amount,
            'delivery_amount' => (float) $order->delivery_amount,
            'total_amount' => (float) $order->total_amount,
            'notes' => $order->notes,
            'receipt_count' => (int) $order->goods_receipts_count,
            'return_count' => (int) $order->purchase_returns_count,
            'items' => $order->items->map(fn (PurchaseOrderItem $item): array => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'sku' => $item->sku,
                'unit' => $item->unit,
                'quantity' => $item->quantity,
                'received_quantity' => $item->received_quantity,
                'remaining_quantity' => $item->remainingQuantity(),
                'returned_quantity' => $item->returned_quantity,
                'returnable_quantity' => $item->returnableQuantity(),
                'unit_price' => (float) $item->unit_price,
                'tax_percent' => (float) $item->tax_percent,
                'line_total' => (float) $item->line_total,
                'warehouse_place_id' => $item->warehouse_place_id,
                'warehouse_place' => $item->warehousePlace ? $this->placePath($item->warehousePlace) : null,
                'warehouse_item_id' => $item->warehouse_item_id,
                'warehouse_stock' => $item->warehouseItem?->quantity,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function goodsReceipt(GoodsReceipt $receipt): array
    {
        return [
            'id' => $receipt->id,
            'number' => $receipt->number,
            'purchase_order_id' => $receipt->purchase_order_id,
            'order_number' => $receipt->purchaseOrder->number,
            'supplier_name' => $receipt->purchaseOrder->supplier->name,
            'currency' => $receipt->purchaseOrder->currency,
            'status' => $receipt->status,
            'received_at' => $receipt->received_at->toISOString(),
            'received_by' => $this->userName($receipt->receiver),
            'external_reference' => $receipt->external_reference,
            'notes' => $receipt->notes,
            'total_amount' => round((float) $receipt->items->sum('line_total'), 2),
            'items' => $receipt->items->map(fn (GoodsReceiptItem $item): array => [
                'id' => $item->id,
                'purchase_order_item_id' => $item->purchase_order_item_id,
                'item_name' => $item->purchaseOrderItem->item_name,
                'sku' => $item->purchaseOrderItem->sku,
                'unit' => $item->purchaseOrderItem->unit,
                'warehouse_item_id' => $item->warehouse_item_id,
                'warehouse_item_name' => $item->warehouseItem->name,
                'quantity' => $item->quantity,
                'returned_quantity' => $item->returnedQuantity(),
                'returnable_quantity' => $item->returnableQuantity(),
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function purchaseReturn(PurchaseReturn $return): array
    {
        return [
            'id' => $return->id,
            'number' => $return->number,
            'purchase_order_id' => $return->purchase_order_id,
            'order_number' => $return->purchaseOrder->number,
            'supplier_name' => $return->purchaseOrder->supplier->name,
            'currency' => $return->purchaseOrder->currency,
            'status' => $return->status,
            'returned_at' => $return->returned_at->toISOString(),
            'created_by' => $this->userName($return->creator),
            'reason' => $return->reason,
            'total_amount' => (float) $return->total_amount,
            'items' => $return->items->map(fn ($item): array => [
                'id' => $item->id,
                'item_name' => $item->purchaseOrderItem->item_name,
                'sku' => $item->purchaseOrderItem->sku,
                'unit' => $item->purchaseOrderItem->unit,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
                'warehouse_stock' => $item->warehouseItem->quantity,
            ])->all(),
        ];
    }

    private function placePath(WarehousePlace $place): string
    {
        return collect([
            $place->floor?->column?->row?->warehouse?->name,
            $place->floor?->column?->row?->name,
            $place->floor?->column?->name,
            $place->floor?->name,
            $place->name,
        ])->filter()->implode(' / ');
    }

    private function userName(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return trim(implode(' ', array_filter([$user->name, $user->last_name])));
    }
}
