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
        Schema::create('crm_funnel_stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crm_funnel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20)->nullable();
            $table->string('type', 20)->default('open');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['crm_funnel_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_funnel_stages');
    }
};
