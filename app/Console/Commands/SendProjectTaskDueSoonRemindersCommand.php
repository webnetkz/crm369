<?php

namespace App\Console\Commands;

use App\Models\ProjectTask;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Lang;

#[Signature('projects:send-due-reminders')]
#[Description('Send one-time reminders for tasks that are due within the next 24 hours')]
class SendProjectTaskDueSoonRemindersCommand extends Command
{
    public function handle(): int
    {
        $windowStartsAt = now();
        $windowEndsAt = $windowStartsAt->copy()->addDay();
        $sentCount = 0;

        ProjectTask::query()
            ->dueSoonReminderPending($windowStartsAt, $windowEndsAt)
            ->with('assignee:id,name,last_name,email,language,has_selected_language')
            ->chunkById(100, function ($tasks) use (&$sentCount): void {
                foreach ($tasks as $task) {
                    $assignee = $task->assignee;

                    if (! $assignee instanceof User) {
                        continue;
                    }

                    $assignee->notify($this->notificationFor($assignee, $task));

                    $task->forceFill([
                        'due_reminder_sent_at' => now(),
                    ])->save();

                    $sentCount++;
                }
            });

        $this->info("Sent {$sentCount} task due reminder(s).");

        return self::SUCCESS;
    }

    private function notificationFor(User $user, ProjectTask $task): SystemNotification
    {
        return new SystemNotification(
            title: Lang::get('ui.notifications.task_due_soon_title', [], $user->resolvedLanguage()),
            message: Lang::get('ui.notifications.task_due_soon_message', [
                'title' => $task->title,
            ], $user->resolvedLanguage()),
            actionUrl: route('projects.workspace.tasks.show', $task),
            actionLabel: Lang::get('ui.notifications.open_target', [], $user->resolvedLanguage()),
        );
    }
}
