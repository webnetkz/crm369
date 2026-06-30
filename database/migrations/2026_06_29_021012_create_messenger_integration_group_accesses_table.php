<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_integration_group_accesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('messenger_integration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_group_id')->constrained()->cascadeOnDelete();
            $table->string('access_level');
            $table->timestamps();

            $table->unique(['messenger_integration_id', 'user_group_id']);
            $table->index(['user_group_id', 'access_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_integration_group_accesses');
    }
};
