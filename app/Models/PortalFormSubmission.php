<?php

namespace App\Models;

use Database\Factories\PortalFormSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $portal_form_id
 * @property int|null $target_user_id
 * @property array<int, array<string, mixed>>|null $payload
 * @property int|null $project_task_id
 * @property int|null $chat_conversation_id
 * @property int|null $chat_message_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['portal_form_id', 'target_user_id', 'payload', 'project_task_id', 'chat_conversation_id', 'chat_message_id'])]
class PortalFormSubmission extends Model
{
    /** @use HasFactory<PortalFormSubmissionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'portal_form_id' => 'integer',
            'target_user_id' => 'integer',
            'payload' => 'array',
            'project_task_id' => 'integer',
            'chat_conversation_id' => 'integer',
            'chat_message_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PortalForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(PortalForm::class, 'portal_form_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * @return BelongsTo<ProjectTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    /**
     * @return BelongsTo<ChatConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    /**
     * @return BelongsTo<ChatMessage, $this>
     */
    public function chatMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }
}
