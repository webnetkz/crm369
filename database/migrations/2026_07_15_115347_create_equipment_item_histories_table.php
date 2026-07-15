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
        Schema::create('equipment_item_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_item_id')->constrained('equipment_items')->cascadeOnDelete();
            $table->string('event_type', 32);
            $table->string('source', 32);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('changes')->nullable();
            $table->json('snapshot');
            $table->dateTime('changed_at');
            $table->timestamps();

            $table->index(['equipment_item_id', 'changed_at']);
            $table->index(['source', 'changed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_item_histories');
    }
};
