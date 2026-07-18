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
        Schema::create('supplier_quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained();
            $table->decimal('unit_price', 14, 2);
            $table->char('currency', 3)->default('KZT');
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('delivery_cost', 14, 2)->default(0);
            $table->date('quoted_at');
            $table->date('valid_until')->nullable();
            $table->unsignedSmallInteger('lead_time_days')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['purchase_request_item_id', 'supplier_id']);
            $table->index(['supplier_id', 'quoted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_quotations');
    }
};
