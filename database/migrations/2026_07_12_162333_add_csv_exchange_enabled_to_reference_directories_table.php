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
        Schema::table('reference_directories', function (Blueprint $table): void {
            $table->boolean('csv_exchange_enabled')
                ->default(true)
                ->after('columns');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reference_directories', function (Blueprint $table): void {
            $table->dropColumn('csv_exchange_enabled');
        });
    }
};
