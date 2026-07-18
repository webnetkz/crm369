<?php

namespace App\Support;

use App\Models\Conference;
use App\Models\PortalSetting;
use App\Models\ProjectTask;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CalendarEventService
{
    /**
     * @param  array<int, 'task'|'conference'>  $types
     * @return Collection<int, array<string, mixed>>
     */
    public function eventsFor(User $user, CarbonInterface $from, CarbonInterface $to, array $types): Collection
    {
        abort_unless($user->canAccessCalendar(), 403);

        $settings = PortalSetting::current();
        $events = collect();

        if (
            in_array('task', $types, true)
            && $settings->isModuleEnabled('projects')
            && $user->canAccessProjects()
        ) {
            $events = $events->concat($this->taskEvents($user, $from, $to));
        }

        if (
            in_array('conference', $types, true)
            && $settings->isModuleEnabled('conferences')
            && $user->canAccessConferences()
        ) {
            $events = $events->concat($this->conferenceEvents($user, $from, $to));
        }

        return $events
            ->sortBy([
                ['start_at', 'asc'],
                ['type', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function taskEvents(User $user, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return ProjectTask::query()
            ->select([
                'id',
                'project_id',
                'creator_user_id',
                'assignee_user_id',
                'title',
                'description',
                'status',
                'importance',
                'due_at',
                'completed_at',
            ])
            ->visibleTo($user)
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$from, $to])
            ->with([
                'project:id,name,slug',
                'assignee:id,name,last_name,email',
            ])
            ->orderBy('due_at')
            ->orderBy('id')
            ->get()
            ->map(fn (ProjectTask $task): array => $this->taskPayload($task));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function conferenceEvents(User $user, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Conference::query()
            ->select([
                'id',
                'title',
                'description',
                'created_by_user_id',
                'starts_at',
                'ended_at',
            ])
            ->visibleTo($user)
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', $to)
            ->where(function (Builder $query) use ($from): void {
                $query
                    ->where('ended_at', '>=', $from)
                    ->orWhere(function (Builder $scheduledQuery) use ($from): void {
                        $scheduledQuery
                            ->whereNull('ended_at')
                            ->where('starts_at', '>=', $from->copy()->subHour());
                    });
            })
            ->with('creator:id,name,last_name,email')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Conference $conference): array => $this->conferencePayload($conference, $user));
    }

    /** @return array<string, mixed> */
    private function taskPayload(ProjectTask $task): array
    {
        $assigneeName = trim(($task->assignee?->name ?? '').' '.($task->assignee?->last_name ?? ''));

        return [
            'id' => 'task:'.$task->id,
            'source_id' => $task->id,
            'type' => 'task',
            'title' => $task->title,
            'description' => $task->description,
            'start_at' => $task->due_at?->toISOString(),
            'end_at' => $task->due_at?->copy()->addMinutes(30)->toISOString(),
            'all_day' => false,
            'status' => $task->status,
            'color' => $this->taskColor($task),
            'url' => $task->project_id !== null
                ? route('projects.tasks.show', [$task->project_id, $task->id])
                : route('tasks.show', $task->id),
            'meta' => [
                'project' => $task->project
                    ? ['id' => $task->project->id, 'name' => $task->project->name]
                    : null,
                'assignee' => $task->assignee
                    ? [
                        'id' => $task->assignee->id,
                        'name' => $assigneeName !== '' ? $assigneeName : $task->assignee->email,
                    ]
                    : null,
                'importance' => $task->importance,
                'is_completed' => $task->completed_at !== null || $task->status === ProjectTask::STATUS_DONE,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function conferencePayload(Conference $conference, User $user): array
    {
        $startsAt = $conference->starts_at;
        $endedAt = $startsAt !== null && $conference->ended_at !== null && $conference->ended_at->greaterThan($startsAt)
            ? $conference->ended_at
            : $startsAt?->copy()->addHour();
        $creatorName = trim(($conference->creator?->name ?? '').' '.($conference->creator?->last_name ?? ''));

        return [
            'id' => 'conference:'.$conference->id,
            'source_id' => $conference->id,
            'type' => 'conference',
            'title' => $conference->title,
            'description' => $conference->description,
            'start_at' => $startsAt?->toISOString(),
            'end_at' => $endedAt?->toISOString(),
            'all_day' => false,
            'status' => $conference->status(),
            'color' => '#7c3aed',
            'url' => route('conferences.show', $conference->id),
            'meta' => [
                'organizer' => $conference->creator
                    ? [
                        'id' => $conference->creator->id,
                        'name' => $creatorName !== '' ? $creatorName : $conference->creator->email,
                    ]
                    : null,
                'is_organizer' => $conference->created_by_user_id === $user->id,
            ],
        ];
    }

    private function taskColor(ProjectTask $task): string
    {
        return match ($task->importance) {
            ProjectTask::IMPORTANCE_CRITICAL => '#dc2626',
            ProjectTask::IMPORTANCE_HIGH => '#ea580c',
            ProjectTask::IMPORTANCE_LOW => '#0f766e',
            default => '#2563eb',
        };
    }
}
