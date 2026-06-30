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
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('language');
            $table->unsignedTinyInteger('avatar_position_x')->default(50)->after('avatar_path');
            $table->unsignedTinyInteger('avatar_position_y')->default(50)->after('avatar_position_x');
            $table->decimal('avatar_scale', 4, 2)->default(1)->after('avatar_position_y');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'avatar_path',
                'avatar_position_x',
                'avatar_position_y',
                'avatar_scale',
            ]);
        });
    }
};
