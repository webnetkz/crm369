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
        Schema::create('one_c_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('product')->default('enterprise_management');
            $table->string('transport')->default('odata');
            $table->boolean('is_enabled')->default(false);
            $table->text('base_url')->nullable();
            $table->string('api_path', 1024)->default('/odata/standard.odata');
            $table->string('auth_type')->default('basic');
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->text('token')->nullable();
            $table->boolean('verify_tls')->default(true);
            $table->unsignedSmallInteger('connect_timeout_seconds')->default(5);
            $table->unsignedSmallInteger('request_timeout_seconds')->default(30);
            $table->boolean('import_enabled')->default(true);
            $table->boolean('export_enabled')->default(false);
            $table->boolean('schedule_enabled')->default(false);
            $table->unsignedSmallInteger('sync_interval_minutes')->default(60);
            $table->time('sync_window_start')->nullable();
            $table->time('sync_window_end')->nullable();
            $table->unsignedSmallInteger('batch_size')->default(100);
            $table->string('default_sync_mode')->default('incremental');
            $table->string('conflict_strategy')->default('one_c_wins');
            $table->boolean('stop_on_error')->default(true);
            $table->boolean('dry_run')->default(false);
            $table->json('entities')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('last_test_succeeded')->nullable();
            $table->unsignedInteger('last_test_duration_ms')->nullable();
            $table->text('last_test_message')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_successful_sync_at')->nullable();
            $table->string('last_sync_status')->nullable();
            $table->text('last_sync_message')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_enabled', 'schedule_enabled']);
            $table->index('product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('one_c_integrations');
    }
};
