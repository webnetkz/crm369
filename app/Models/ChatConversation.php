<?php

namespace App\Models;

use Database\Factories\ChatConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property string|null $title
 * @property int|null $created_by_user_id
 * @property int|null $project_task_id
 * @property Carbon|null $last_message_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['type', 'title', 'created_by_user_id', 'project_task_id', 'last_message_at'])]
class ChatConversation extends Model
{
    public const string TYPE_DIRECT = 'direct';
    public const string TYPE_TASK = 'task';

    /** @use HasFactory<ChatConversationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ChatConversationParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ChatConversationParticipant::class);
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * @return HasOne<ChatMessage, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<ProjectTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    public function hasParticipant(User $user): bool
    {
        if ($this->relationLoaded('participants')) {
            return $this->participants->contains(
                fn (ChatConversationParticipant $participant): bool => $participant->user_id === $user->id,
            );
        }

        return $this->participants()
            ->where('user_id', $user->id)
            ->exists();
    }

    public function isAccessibleBy(User $user): bool
    {
        if ($this->type === self::TYPE_TASK) {
            $task = $this->relationLoaded('task') ? $this->task : $this->task()->first();

            return $task !== null && $user->canViewTask($task);
        }

        return $this->hasParticipant($user);
    }
}
