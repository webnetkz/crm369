<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table): void {
            $table->foreignId('parent_task_id')
                ->nullable()
                ->after('project_id')
                ->constrained('project_tasks')
                ->nullOnDelete();

            $table->foreignId('project_id')
                ->nullable()
                ->change();

            $table->index(['project_id', 'parent_task_id']);
        });
    }

    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'parent_task_id']);
            $table->dropConstrainedForeignId('parent_task_id');
            $table->foreignId('project_id')
                ->nullable(false)
                ->change();
        });
    }
};
