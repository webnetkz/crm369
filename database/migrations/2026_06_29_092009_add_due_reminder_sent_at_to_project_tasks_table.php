<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table): void {
            $table->timestamp('due_reminder_sent_at')->nullable()->after('due_at');
            $table->index(['due_reminder_sent_at', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table): void {
            $table->dropIndex(['due_reminder_sent_at', 'due_at']);
            $table->dropColumn('due_reminder_sent_at');
        });
    }
};
