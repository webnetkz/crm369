<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_request_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_place_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name');
            $table->string('sku')->nullable();
            $table->string('unit', 32)->default('pcs');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('received_quantity')->default(0);
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['purchase_order_id', 'item_name']);
            $table->index('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
