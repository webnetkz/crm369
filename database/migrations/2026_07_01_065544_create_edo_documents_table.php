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
        Schema::create('edo_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('external_reference')->nullable()->index();
            $table->string('counterparty_name');
            $table->string('counterparty_email')->nullable();
            $table->longText('content');
            $table->string('status')->default('draft')->index();
            $table->string('public_token')->nullable()->unique();
            $table->dateTime('public_link_expires_at')->nullable();
            $table->longText('signature_payload')->nullable();
            $table->string('signature_subject')->nullable();
            $table->string('signature_serial_number')->nullable();
            $table->string('signature_algorithm')->nullable();
            $table->string('signed_payload_hash', 64)->nullable();
            $table->json('signature_metadata')->nullable();
            $table->dateTime('signed_at')->nullable()->index();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('updated_by_user_id')->constrained('users');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edo_documents');
    }
};
