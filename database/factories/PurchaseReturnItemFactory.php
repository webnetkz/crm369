<?php

namespace Database\Factories;

use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\WarehouseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseReturnItem>
 */
class PurchaseReturnItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_return_id' => PurchaseReturn::factory(),
            'goods_receipt_item_id' => GoodsReceiptItem::factory(),
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'warehouse_item_id' => WarehouseItem::factory(),
            'quantity' => fake()->numberBetween(1, 5),
            'unit_price' => fake()->randomFloat(2, 100, 50000),
            'line_total' => fake()->randomFloat(2, 100, 50000),
        ];
    }
}
