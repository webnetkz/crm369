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
        Schema::create('file_directory_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_directory_id')->constrained('file_directories')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_group_id')->nullable()->constrained('user_groups')->cascadeOnDelete();
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('access_level');
            $table->timestamps();

            $table->unique(['file_directory_id', 'user_id']);
            $table->unique(['file_directory_id', 'user_group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_directory_permissions');
    }
};
