<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectTask>
 */
class ProjectTaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'parent_task_id' => null,
            'creator_user_id' => User::factory(),
            'assignee_user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(ProjectTask::availableStatuses()),
            'importance' => fake()->randomElement(ProjectTask::availableImportances()),
            'complexity' => fake()->numberBetween(1, 10),
            'due_at' => fake()->dateTimeBetween('now', '+14 days'),
            'due_reminder_sent_at' => null,
            'completed_at' => null,
            'sort_order' => 0,
            'updated_by_user_id' => User::factory(),
        ];
    }

    public function standalone(): static
    {
        return $this->state(fn (): array => [
            'project_id' => null,
        ]);
    }
}
