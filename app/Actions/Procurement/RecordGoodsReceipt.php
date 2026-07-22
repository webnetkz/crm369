<?php

namespace App\Actions\Procurement;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Models\WarehousePlace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecordGoodsReceipt
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array<string, int>>  $items
     */
    public function handle(array $payload, array $items, ?User $user): GoodsReceipt
    {
        return DB::transaction(function () use ($payload, $items, $user): GoodsReceipt {
            $purchaseOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($payload['purchase_order_id']);

            if (! in_array($purchaseOrder->status, [PurchaseOrder::STATUS_SENT, PurchaseOrder::STATUS_PARTIALLY_RECEIVED], true)) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => __('ui.procurement.validation.order_not_receivable'),
                ]);
            }

            $goodsReceipt = GoodsReceipt::query()->create([
                ...$payload,
                'number' => $this->number(),
                'status' => GoodsReceipt::STATUS_POSTED,
                'received_by_user_id' => $user?->id,
            ]);

            foreach ($items as $index => $itemPayload) {
                $orderItem = PurchaseOrderItem::query()
                    ->lockForUpdate()
                    ->findOrFail($itemPayload['purchase_order_item_id']);

                if ($orderItem->purchase_order_id !== $purchaseOrder->id) {
                    throw ValidationException::withMessages([
                        "items.{$index}.purchase_order_item_id" => __('ui.procurement.validation.item_not_in_order'),
                    ]);
                }

                $quantity = (int) $itemPayload['quantity'];

                if ($quantity > $orderItem->remainingQuantity()) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => __('ui.procurement.validation.receipt_exceeds_remaining'),
                    ]);
                }

                $warehousePlace = WarehousePlace::query()
                    ->lockForUpdate()
                    ->findOrFail($itemPayload['warehouse_place_id']);
                $warehouseItem = $this->warehouseItem($orderItem, $warehousePlace);

                $warehouseItem->increment('quantity', $quantity);
                $orderItem->update([
                    'warehouse_place_id' => $warehousePlace->id,
                    'warehouse_item_id' => $warehouseItem->id,
                    'received_quantity' => $orderItem->received_quantity + $quantity,
                ]);

                $goodsReceipt->items()->create([
                    'purchase_order_item_id' => $orderItem->id,
                    'warehouse_item_id' => $warehouseItem->id,
                    'warehouse_place_id' => $warehousePlace->id,
                    'quantity' => $quantity,
                    'unit_price' => $orderItem->unit_price,
                    'line_total' => round($quantity * (float) $orderItem->unit_price, 2),
                ]);
            }

            $fullyReceived = ! $purchaseOrder->items()
                ->whereColumn('received_quantity', '<', 'quantity')
                ->exists();
            $status = $fullyReceived
                ? PurchaseOrder::STATUS_RECEIVED
                : PurchaseOrder::STATUS_PARTIALLY_RECEIVED;

            $purchaseOrder->update(['status' => $status]);
            $purchaseOrder->purchaseRequest?->update([
                'status' => $fullyReceived
                    ? PurchaseRequest::STATUS_RECEIVED
                    : PurchaseRequest::STATUS_PARTIALLY_RECEIVED,
            ]);

            return $goodsReceipt->load(['purchaseOrder.supplier', 'items.warehouseItem']);
        }, 3);
    }

    private function warehouseItem(PurchaseOrderItem $orderItem, WarehousePlace $warehousePlace): WarehouseItem
    {
        if ($orderItem->warehouse_item_id !== null) {
            $warehouseItem = WarehouseItem::query()->lockForUpdate()->findOrFail($orderItem->warehouse_item_id);

            if ($warehouseItem->warehouse_place_id !== $warehousePlace->id) {
                throw ValidationException::withMessages([
                    'items' => __('ui.procurement.validation.warehouse_place_mismatch'),
                ]);
            }

            return $warehouseItem;
        }

        $warehouseItem = WarehouseItem::query()
            ->where('warehouse_place_id', $warehousePlace->id)
            ->when(
                $orderItem->sku !== null,
                fn ($query) => $query->where('sku', $orderItem->sku),
                fn ($query) => $query->whereNull('sku')->where('name', $orderItem->item_name),
            )
            ->lockForUpdate()
            ->first();

        return $warehouseItem ?? WarehouseItem::query()->create([
            'warehouse_place_id' => $warehousePlace->id,
            'name' => $orderItem->item_name,
            'sku' => $orderItem->sku,
            'quantity' => 0,
            'notes' => __('ui.procurement.inventory_created_note', ['order' => $orderItem->purchaseOrder->number]),
        ]);
    }

    private function number(): string
    {
        return 'GR-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
    }
}
