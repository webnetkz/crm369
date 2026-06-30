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
        Schema::create('portal_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portal_form_id')->constrained('portal_forms')->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('type');
            $table->string('placeholder')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['portal_form_id', 'key']);
            $table->index(['portal_form_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portal_form_fields');
    }
};
