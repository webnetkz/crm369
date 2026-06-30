<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ProjectTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $project_id
 * @property int|null $parent_task_id
 * @property int|null $creator_user_id
 * @property int|null $assignee_user_id
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property string $importance
 * @property int $complexity
 * @property Carbon|null $due_at
 * @property Carbon|null $due_reminder_sent_at
 * @property Carbon|null $completed_at
 * @property int $sort_order
 * @property int|null $updated_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['project_id', 'parent_task_id', 'creator_user_id', 'assignee_user_id', 'title', 'description', 'status', 'importance', 'complexity', 'due_at', 'due_reminder_sent_at', 'completed_at', 'sort_order', 'updated_by_user_id'])]
class ProjectTask extends Model
{
    public const string STATUS_TODO = 'todo';
    public const string STATUS_IN_PROGRESS = 'in_progress';
    public const string STATUS_REVIEW = 'review';
    public const string STATUS_DONE = 'done';

    public const string IMPORTANCE_LOW = 'low';
    public const string IMPORTANCE_NORMAL = 'normal';
    public const string IMPORTANCE_HIGH = 'high';
    public const string IMPORTANCE_CRITICAL = 'critical';

    /** @use HasFactory<ProjectTaskFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'complexity' => 'integer',
            'sort_order' => 'integer',
            'due_at' => 'datetime',
            'due_reminder_sent_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<ProjectTask>  $query
     * @return Builder<ProjectTask>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $taskQuery) use ($user): void {
            $taskQuery
                ->where(function (Builder $projectTaskQuery) use ($user): void {
                    $projectTaskQuery
                        ->whereNotNull('project_id')
                        ->whereHas('project', fn (Builder $projectQuery): Builder => $projectQuery->visibleTo($user));
                })
                ->orWhere(function (Builder $standaloneTaskQuery) use ($user): void {
                    $standaloneTaskQuery
                        ->whereNull('project_id')
                        ->where(function (Builder $participantQuery) use ($user): void {
                            $participantQuery
                                ->where('creator_user_id', $user->id)
                                ->orWhere('assignee_user_id', $user->id)
                                ->orWhereHas('coAssignees', fn (Builder $coAssigneeQuery): Builder => $coAssigneeQuery->whereKey($user->id));
                        });
                });
        });
    }

    /**
     * @param  Builder<ProjectTask>  $query
     * @return Builder<ProjectTask>
     */
    public function scopeDueSoonReminderPending(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query
            ->whereNull('due_reminder_sent_at')
            ->whereNotNull('due_at')
            ->whereNotNull('assignee_user_id')
            ->where('status', '!=', self::STATUS_DONE)
            ->whereBetween('due_at', [$from, $to])
            ->whereHas('assignee', fn (Builder $assigneeQuery): Builder => $assigneeQuery->where('is_active', true));
    }

    /**
     * @return array<int, string>
     */
    public static function availableStatuses(): array
    {
        return [
            self::STATUS_TODO,
            self::STATUS_IN_PROGRESS,
            self::STATUS_REVIEW,
            self::STATUS_DONE,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function availableImportances(): array
    {
        return [
            self::IMPORTANCE_LOW,
            self::IMPORTANCE_NORMAL,
            self::IMPORTANCE_HIGH,
            self::IMPORTANCE_CRITICAL,
        ];
    }

    public function dueReminderNeedsReset(?Carbon $dueAt, ?int $assigneeUserId): bool
    {
        if ($this->due_reminder_sent_at === null) {
            return false;
        }

        $dueAtChanged = match (true) {
            $this->due_at === null && $dueAt !== null => true,
            $this->due_at !== null && $dueAt === null => true,
            $this->due_at !== null && $dueAt !== null => ! $this->due_at->equalTo($dueAt),
            default => false,
        };

        return $dueAtChanged || $this->assignee_user_id !== $assigneeUserId;
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<ProjectTask, $this>
     */
    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    /**
     * @return HasMany<ProjectTask, $this>
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_task_id')
            ->orderBy('status')
            ->orderBy('sort_order')
            ->orderBy('due_at')
            ->orderByDesc('created_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function coAssignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_task_user')
            ->withTimestamps()
            ->orderBy('name');
    }

    /**
     * @return HasOne<ChatConversation, $this>
     */
    public function chatConversation(): HasOne
    {
        return $this->hasOne(ChatConversation::class, 'project_task_id');
    }
}
