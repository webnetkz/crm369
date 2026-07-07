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
        Schema::create('warehouse_columns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_row_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('qr_code')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['warehouse_row_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_columns');
    }
};
