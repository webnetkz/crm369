<?php

namespace App\Models;

use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $chat_conversation_id
 * @property int $user_id
 * @property string $body
 * @property string|null $original_body
 * @property Collection<int, ChatMessageAttachment> $attachments
 * @property Carbon|null $edited_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $pinned_at
 * @property int|null $pinned_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['chat_conversation_id', 'user_id', 'body', 'original_body', 'edited_at', 'deleted_at', 'pinned_at', 'pinned_by_user_id'])]
class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
            'pinned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ChatConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pinnedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by_user_id');
    }

    /**
     * @return HasMany<ChatMessageAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ChatMessageAttachment::class);
    }

    public function wasEdited(): bool
    {
        return $this->edited_at !== null;
    }

    public function wasDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    public function isPinned(): bool
    {
        return $this->pinned_at !== null;
    }
}
