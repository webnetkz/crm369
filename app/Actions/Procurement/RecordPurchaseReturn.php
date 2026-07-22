<?php

namespace App\Actions\Procurement;

use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecordPurchaseReturn
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array<string, int>>  $items
     */
    public function handle(array $payload, array $items, ?User $user): PurchaseReturn
    {
        return DB::transaction(function () use ($payload, $items, $user): PurchaseReturn {
            $purchaseOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($payload['purchase_order_id']);
            $totalAmount = 0.0;
            $returnItemPayloads = [];

            foreach ($items as $index => $itemPayload) {
                $receiptItem = GoodsReceiptItem::query()
                    ->with('goodsReceipt:id,purchase_order_id')
                    ->lockForUpdate()
                    ->findOrFail($itemPayload['goods_receipt_item_id']);

                if ($receiptItem->goodsReceipt->purchase_order_id !== $purchaseOrder->id) {
                    throw ValidationException::withMessages([
                        "items.{$index}.goods_receipt_item_id" => __('ui.procurement.validation.receipt_item_not_in_order'),
                    ]);
                }

                $quantity = (int) $itemPayload['quantity'];
                $alreadyReturned = (int) $receiptItem->purchaseReturnItems()->sum('quantity');

                if ($quantity > $receiptItem->quantity - $alreadyReturned) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => __('ui.procurement.validation.return_exceeds_received'),
                    ]);
                }

                $warehouseItem = WarehouseItem::query()->lockForUpdate()->findOrFail($receiptItem->warehouse_item_id);

                if ($warehouseItem->quantity < $quantity) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => __('ui.procurement.validation.insufficient_stock'),
                    ]);
                }

                $orderItem = PurchaseOrderItem::query()->lockForUpdate()->findOrFail($receiptItem->purchase_order_item_id);
                $lineTotal = round($quantity * (float) $receiptItem->unit_price, 2);

                $warehouseItem->decrement('quantity', $quantity);
                $orderItem->update([
                    'returned_quantity' => $orderItem->returned_quantity + $quantity,
                ]);
                $totalAmount += $lineTotal;
                $returnItemPayloads[] = [
                    'goods_receipt_item_id' => $receiptItem->id,
                    'purchase_order_item_id' => $orderItem->id,
                    'warehouse_item_id' => $warehouseItem->id,
                    'quantity' => $quantity,
                    'unit_price' => $receiptItem->unit_price,
                    'line_total' => $lineTotal,
                ];
            }

            $purchaseReturn = PurchaseReturn::query()->create([
                ...$payload,
                'number' => $this->number(),
                'status' => PurchaseReturn::STATUS_POSTED,
                'created_by_user_id' => $user?->id,
                'total_amount' => round($totalAmount, 2),
            ]);
            $purchaseReturn->items()->createMany($returnItemPayloads);

            return $purchaseReturn->load(['purchaseOrder.supplier', 'items.warehouseItem']);
        }, 3);
    }

    private function number(): string
    {
        return 'RT-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
    }
}
