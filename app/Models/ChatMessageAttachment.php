<?php

namespace App\Models;

use Database\Factories\ChatMessageAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $chat_message_id
 * @property string $original_name
 * @property string $disk
 * @property string $path
 * @property string|null $mime_type
 * @property string|null $extension
 * @property int $size_bytes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['chat_message_id', 'original_name', 'disk', 'path', 'mime_type', 'extension', 'size_bytes'])]
class ChatMessageAttachment extends Model
{
    /** @use HasFactory<ChatMessageAttachmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chat_message_id' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ChatMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }
}
