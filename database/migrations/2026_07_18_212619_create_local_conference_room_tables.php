<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conference_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conference_id')->constrained('conferences')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('display_name', 120);
            $table->char('access_token_hash', 64)->unique();
            $table->boolean('is_guest')->default(false);
            $table->dateTime('joined_at');
            $table->dateTime('last_seen_at');
            $table->dateTime('left_at')->nullable();
            $table->timestamps();

            $table->index(['conference_id', 'left_at', 'last_seen_at']);
            $table->index(['conference_id', 'user_id']);
        });

        Schema::create('conference_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conference_id')->constrained('conferences')->cascadeOnDelete();
            $table->foreignId('sender_participant_id')->constrained('conference_participants')->cascadeOnDelete();
            $table->foreignId('recipient_participant_id')->constrained('conference_participants')->cascadeOnDelete();
            $table->string('type', 32);
            $table->json('payload');
            $table->dateTime('expires_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['recipient_participant_id', 'id']);
            $table->index(['conference_id', 'expires_at']);
        });

        Schema::create('conference_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conference_id')->constrained('conferences')->cascadeOnDelete();
            $table->foreignId('participant_id')->nullable()->constrained('conference_participants')->nullOnDelete();
            $table->string('display_name', 120);
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['conference_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conference_messages');
        Schema::dropIfExists('conference_signals');
        Schema::dropIfExists('conference_participants');
    }
};
