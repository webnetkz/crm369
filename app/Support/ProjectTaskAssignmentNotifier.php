<?php

namespace App\Support;

use App\Models\ProjectTask;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Lang;

class ProjectTaskAssignmentNotifier
{
    public function sendForManualCreation(ProjectTask $task, User $actor): void
    {
        $task->loadMissing('assignee:id,name,last_name,email,language,has_selected_language');

        $assignee = $task->assignee;

        if (! $assignee instanceof User || $assignee->is($actor)) {
            return;
        }

        $locale = $assignee->resolvedLanguage();

        $assignee->notify(new SystemNotification(
            title: Lang::get('ui.notifications.task_assigned_title', [], $locale),
            message: Lang::get('ui.notifications.task_assigned_message', [
                'title' => $task->title,
                'user' => $this->displayName($actor),
            ], $locale),
            actionUrl: route('projects.workspace.tasks.show', $task),
            actionLabel: Lang::get('ui.notifications.open_target', [], $locale),
        ));
    }

    public function sendForFormCreation(ProjectTask $task, string $formName): void
    {
        $task->loadMissing('assignee:id,name,last_name,email,language,has_selected_language');

        $assignee = $task->assignee;

        if (! $assignee instanceof User) {
            return;
        }

        $locale = $assignee->resolvedLanguage();

        $assignee->notify(new SystemNotification(
            title: Lang::get('ui.notifications.task_assigned_title', [], $locale),
            message: Lang::get('ui.notifications.task_assigned_from_form_message', [
                'title' => $task->title,
                'form' => $formName,
            ], $locale),
            actionUrl: route('projects.workspace.tasks.show', $task),
            actionLabel: Lang::get('ui.notifications.open_target', [], $locale),
        ));
    }

    private function displayName(User $user): string
    {
        $fullName = trim($user->name.' '.($user->last_name ?? ''));

        return $fullName !== '' ? $fullName : $user->email;
    }
}
