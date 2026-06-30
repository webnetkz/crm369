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
            $table->string('background_color')->nullable()->after('language');
            $table->string('background_image_path')->nullable()->after('background_color');
            $table->unsignedTinyInteger('background_blur')->default(0)->after('background_image_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'background_color',
                'background_image_path',
                'background_blur',
            ]);
        });
    }
};
