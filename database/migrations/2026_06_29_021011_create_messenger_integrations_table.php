<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messenger_integrations', function (Blueprint $table): void {
            $table->id();
            $table->string('driver')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(false);
            $table->json('settings')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['driver', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messenger_integrations');
    }
};
