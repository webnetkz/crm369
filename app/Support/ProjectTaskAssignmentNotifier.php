<?php

namespace App\Support;

use App\Models\ProjectTask;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Lang;

class ProjectTaskAssignmentNotifier
{
    /**
     * @param  array<int, int>  $previousCoAssigneeUserIds
     */
    public function sendForAssignmentChanges(
        ProjectTask $task,
        User $actor,
        ?int $previousAssigneeUserId = null,
        array $previousCoAssigneeUserIds = [],
    ): void {
        $task->load([
            'assignee:id,name,last_name,email,language,has_selected_language',
            'coAssignees:id,name,last_name,email,language,has_selected_language',
        ]);

        $assignee = $task->assignee;

        if (
            $assignee instanceof User
            && $assignee->id !== $previousAssigneeUserId
            && ! $assignee->is($actor)
        ) {
            $this->sendManualAssignmentNotification($assignee, $task, $actor);
        }

        $task->coAssignees
            ->reject(fn (User $coAssignee): bool => in_array($coAssignee->id, $previousCoAssigneeUserIds, true))
            ->reject(fn (User $coAssignee): bool => $coAssignee->is($actor))
            ->each(fn (User $coAssignee) => $this->sendCoAssigneeNotification($coAssignee, $task, $actor));
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

    private function sendManualAssignmentNotification(User $assignee, ProjectTask $task, User $actor): void
    {
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

    private function sendCoAssigneeNotification(User $coAssignee, ProjectTask $task, User $actor): void
    {
        $locale = $coAssignee->resolvedLanguage();

        $coAssignee->notify(new SystemNotification(
            title: Lang::get('ui.notifications.task_co_assignee_added_title', [], $locale),
            message: Lang::get('ui.notifications.task_co_assignee_added_message', [
                'title' => $task->title,
                'user' => $this->displayName($actor),
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
