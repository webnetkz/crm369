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
        Schema::table('portal_forms', function (Blueprint $table) {
            $table->json('completion_settings')->nullable()->after('style_settings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('portal_forms', function (Blueprint $table) {
            $table->dropColumn('completion_settings');
        });
    }
};
