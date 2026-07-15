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
        Schema::create('conferences', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('room_name')->unique();
            $table->string('public_token')->unique();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->dateTime('starts_at')->nullable()->index();
            $table->dateTime('ended_at')->nullable()->index();
            $table->boolean('allow_external_guests')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conferences');
    }
};
