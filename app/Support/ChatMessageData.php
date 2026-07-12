<?php

namespace App\Support;

use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\User;

class ChatMessageData
{
    private const NON_PREVIEWABLE_IMAGE_MIME_TYPES = [
        'image/svg',
        'image/svg+xml',
    ];

    /**
     * @return array<string, mixed>
     */
    public function serialize(ChatMessage $message, User $viewer): array
    {
        $message->loadMissing([
            'user:id,name,last_name,email,phone,avatar_path,avatar_scale,user_group_id',
            'attachments',
        ]);

        $isDeleted = $message->wasDeleted();

        return [
            'id' => $message->id,
            'body' => $isDeleted ? __('ui.chat.deleted_message') : $message->body,
            'createdAt' => $message->created_at?->toISOString(),
            'editedAt' => $isDeleted ? null : $message->edited_at?->toISOString(),
            'deletedAt' => $message->deleted_at?->toISOString(),
            'isEdited' => ! $isDeleted && $message->wasEdited(),
            'isDeleted' => $isDeleted,
            'isOwn' => $message->user_id === $viewer->id,
            'user' => $this->serializeUserSummary($message->user),
            'attachments' => $isDeleted
                ? []
                : $message->attachments
                    ->map(fn (ChatMessageAttachment $attachment): array => $this->serializeAttachment($attachment))
                    ->values()
                    ->all(),
        ];
    }

    public function excerpt(?ChatMessage $message): ?string
    {
        if (! $message) {
            return null;
        }

        if ($message->wasDeleted()) {
            return __('ui.chat.deleted_message');
        }

        $body = trim($message->body);

        if ($body !== '') {
            return $body;
        }

        $message->loadMissing('attachments');

        $attachmentCount = $message->attachments->count();

        if ($attachmentCount === 0) {
            return null;
        }

        $firstName = $message->attachments->first()?->original_name ?? __('ui.chat.attachment');

        return $attachmentCount === 1
            ? $firstName
            : sprintf('%s +%d', $firstName, $attachmentCount - 1);
    }

    /**
     * @return array{id: int, name: string, mimeType: string|null, extension: string|null, sizeBytes: int, downloadUrl: string, previewUrl: string|null}
     */
    public function serializeAttachment(ChatMessageAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'name' => $attachment->original_name,
            'mimeType' => $attachment->mime_type,
            'extension' => $attachment->extension,
            'sizeBytes' => $attachment->size_bytes,
            'downloadUrl' => route('chats.attachments.download', $attachment),
            'previewUrl' => $this->previewUrl($attachment),
        ];
    }

    public function previewUrl(ChatMessageAttachment $attachment): ?string
    {
        if (! $this->isPreviewableImage($attachment)) {
            return null;
        }

        return route('chats.attachments.preview', $attachment);
    }

    public function isPreviewableImage(ChatMessageAttachment $attachment): bool
    {
        $mimeType = strtolower((string) $attachment->mime_type);

        return str_starts_with($mimeType, 'image/')
            && ! in_array($mimeType, self::NON_PREVIEWABLE_IMAGE_MIME_TYPES, true);
    }

    /**
     * @return array{id: int, name: string, email: string, phone: string|null, avatar: string|null, avatarScale: float}
     */
    private function serializeUserSummary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => trim($user->name.' '.($user->last_name ?? '')),
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'avatarScale' => $user->avatar_scale,
        ];
    }
}
