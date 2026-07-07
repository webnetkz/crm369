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
        Schema::create('tsd_qr_scans', function (Blueprint $table) {
            $table->id();
            $table->text('qr_code');
            $table->text('normalized_qr_code');
            $table->string('source', 32);
            $table->string('device_name', 120)->nullable();
            $table->string('location', 120)->nullable();
            $table->string('context', 255)->nullable();
            $table->json('payload')->nullable();
            $table->dateTime('scanned_at');
            $table->foreignId('scanned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('portal_webhook_id')->nullable()->constrained('portal_webhooks')->nullOnDelete();
            $table->timestamps();

            $table->index(['source', 'scanned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tsd_qr_scans');
    }
};
