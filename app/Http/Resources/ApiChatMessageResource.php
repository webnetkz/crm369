<?php

namespace App\Http\Resources;

use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiChatMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ChatMessage $message */
        $message = $this->resource;
        $viewer = $request->user();
        $message->loadMissing(['user', 'attachments']);
        $isDeleted = $message->wasDeleted();

        return [
            'id' => $message->id,
            'chat_conversation_id' => $message->chat_conversation_id,
            'body' => $isDeleted ? __('ui.chat.deleted_message') : $message->body,
            'created_at' => $message->created_at?->toISOString(),
            'edited_at' => $isDeleted ? null : $message->edited_at?->toISOString(),
            'deleted_at' => $message->deleted_at?->toISOString(),
            'updated_at' => $message->updated_at?->toISOString(),
            'is_edited' => ! $isDeleted && $message->wasEdited(),
            'is_deleted' => $isDeleted,
            'is_own' => $viewer ? $message->user_id === $viewer->id : false,
            'user' => $message->user
                ? (new ApiUserResource($message->user))->resolve()
                : null,
            'attachments' => $isDeleted
                ? []
                : $message->attachments
                    ->map(fn (ChatMessageAttachment $attachment): array => [
                        'id' => $attachment->id,
                        'original_name' => $attachment->original_name,
                        'mime_type' => $attachment->mime_type,
                        'extension' => $attachment->extension,
                        'size_bytes' => $attachment->size_bytes,
                        'download_url' => route('chats.attachments.download', $attachment),
                        'preview_url' => $this->previewUrl($attachment),
                        'audio_url' => $this->audioUrl($attachment),
                    ])
                    ->values()
                    ->all(),
        ];
    }

    private function previewUrl(ChatMessageAttachment $attachment): ?string
    {
        $mimeType = strtolower((string) $attachment->mime_type);

        if (! str_starts_with($mimeType, 'image/')) {
            return null;
        }

        if (in_array($mimeType, ['image/svg', 'image/svg+xml'], true)) {
            return null;
        }

        return route('chats.attachments.preview', $attachment);
    }

    private function audioUrl(ChatMessageAttachment $attachment): ?string
    {
        if (! str_starts_with(strtolower((string) $attachment->mime_type), 'audio/')) {
            return null;
        }

        return route('chats.attachments.preview', $attachment);
    }
}
