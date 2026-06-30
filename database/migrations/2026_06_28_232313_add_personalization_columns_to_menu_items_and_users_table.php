<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('type')->constrained()->nullOnDelete();
            $table->boolean('is_global')->default(false)->after('user_id')->index();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('hidden_menu_item_keys')->nullable()->after('background_blur');
            $table->json('hidden_menu_item_ids')->nullable()->after('hidden_menu_item_keys');
        });

        DB::table('menu_items')
            ->where('type', 'custom')
            ->update(['is_global' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'hidden_menu_item_keys',
                'hidden_menu_item_ids',
            ]);
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('is_global');
        });
    }
};
