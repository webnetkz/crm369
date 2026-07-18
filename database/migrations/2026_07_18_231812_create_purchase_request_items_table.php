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
        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_place_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name');
            $table->string('sku')->nullable();
            $table->string('unit', 32)->default('pcs');
            $table->unsignedInteger('quantity');
            $table->decimal('target_unit_price', 14, 2)->default(0);
            $table->string('production_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['purchase_request_id', 'item_name']);
            $table->index('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_request_items');
    }
};
