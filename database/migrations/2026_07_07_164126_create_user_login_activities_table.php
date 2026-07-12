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
        Schema::create('user_login_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->string('browser')->nullable();
            $table->string('platform')->nullable();
            $table->string('device_type', 20)->default('unknown');
            $table->string('device_signature');
            $table->boolean('is_new_device')->default(false);
            $table->boolean('is_new_ip')->default(false);
            $table->timestamp('logged_in_at')->index();
            $table->timestamps();

            $table->index(['user_id', 'logged_in_at']);
            $table->index(['user_id', 'device_signature']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_login_activities');
    }
};
