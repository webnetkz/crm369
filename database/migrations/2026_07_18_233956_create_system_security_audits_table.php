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
        Schema::create('system_security_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('score');
            $table->string('risk_level', 24)->index();
            $table->unsignedSmallInteger('passed_count')->default(0);
            $table->unsignedSmallInteger('warning_count')->default(0);
            $table->unsignedSmallInteger('failed_count')->default(0);
            $table->unsignedSmallInteger('skipped_count')->default(0);
            $table->unsignedSmallInteger('total_count');
            $table->json('checks');
            $table->json('manual_answers');
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('checked_at')->index();
            $table->timestamps();

            $table->index(['performed_by_user_id', 'checked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_security_audits');
    }
};
