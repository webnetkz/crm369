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
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->foreignId('project_task_id')
                ->nullable()
                ->after('created_by_user_id')
                ->constrained('project_tasks')
                ->cascadeOnDelete();

            $table->unique('project_task_id');
            $table->index(['type', 'project_task_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropUnique(['project_task_id']);
            $table->dropIndex(['type', 'project_task_id']);
            $table->dropConstrainedForeignId('project_task_id');
        });
    }
};
