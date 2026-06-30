<?php

use App\Models\ProjectTaskStage;
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
        Schema::create('project_task_stages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name')->nullable();
            $table->string('color', 7)->default('#64748B');
            $table->boolean('is_completed')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        ProjectTaskStage::query()->insert(array_map(
            static function (array $stage): array {
                return [
                    ...$stage,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            },
            ProjectTaskStage::defaultStages(),
        ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_task_stages');
    }
};
