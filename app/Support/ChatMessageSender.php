<?php

namespace App\Support;

use App\Http\Requests\StoreChatMessageRequest;
use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ChatMessageSender
{
    public function __construct(
        private readonly ChatRuntimeCache $chatRuntimeCache,
        private readonly GeneralChatManager $generalChatManager,
    ) {}

    public function sendPlainText(
        ChatConversation $chatConversation,
        User $user,
        string $body,
    ): ChatMessage {
        return DB::transaction(function () use (
            $chatConversation,
            $body,
            $user,
        ): ChatMessage {
            $this->generalChatManager->ensureActiveParticipants($chatConversation);

            $message = $chatConversation->messages()->create([
                'user_id' => $user->id,
                'body' => $body,
            ]);

            $this->touchConversation($chatConversation, $message);

            return $message->load([
                'user:id,name,last_name,email,phone,avatar_path,avatar_scale,user_group_id',
                'attachments',
            ]);
        });
    }

    public function send(
        ChatConversation $chatConversation,
        User $user,
        StoreChatMessageRequest $request,
    ): ChatMessage {
        $storedAttachments = [];

        try {
            return DB::transaction(function () use (
                $chatConversation,
                $request,
                &$storedAttachments,
                $user,
            ): ChatMessage {
                $this->generalChatManager->ensureActiveParticipants($chatConversation);

                $message = $chatConversation->messages()->create([
                    'user_id' => $user->id,
                    'body' => $request->body(),
                ]);

                foreach ($request->attachments() as $attachment) {
                    $storedPath = $this->storeAttachment(
                        $chatConversation,
                        $message,
                        $attachment,
                    );

                    $storedAttachments[] = $storedPath;

                    $originalName = trim(basename(str_replace('\\', '/', (string) $attachment->getClientOriginalName())));
                    $extension = pathinfo($originalName, PATHINFO_EXTENSION);

                    $message->attachments()->create([
                        'original_name' => $originalName !== '' ? $originalName : __('ui.chat.attachment'),
                        'disk' => 'local',
                        'path' => $storedPath,
                        'mime_type' => $attachment->getClientMimeType(),
                        'extension' => $extension !== '' ? $extension : null,
                        'size_bytes' => $attachment->getSize() ?? 0,
                    ]);
                }

                $this->touchConversation($chatConversation, $message);

                return $message->load([
                    'user:id,name,last_name,email,phone,avatar_path,avatar_scale,user_group_id',
                    'attachments',
                ]);
            });
        } catch (Throwable $exception) {
            foreach ($storedAttachments as $storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }
    }

    private function storeAttachment(
        ChatConversation $chatConversation,
        ChatMessage $message,
        UploadedFile $attachment,
    ): string {
        $originalName = trim(basename(str_replace('\\', '/', (string) $attachment->getClientOriginalName())));
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $storedFileName = Str::uuid()->toString().($extension !== '' ? '.'.$extension : '');

        return $attachment->storeAs(
            'chat-attachments/'.$chatConversation->id.'/'.$message->id,
            $storedFileName,
            'local',
        );
    }

    private function touchConversation(
        ChatConversation $chatConversation,
        ChatMessage $message,
    ): void {
        $chatConversation->forceFill([
            'last_message_at' => $message->created_at,
        ])->save();

        ChatConversationParticipant::query()->updateOrCreate(
            [
                'chat_conversation_id' => $chatConversation->id,
                'user_id' => $message->user_id,
            ],
            [
                'last_read_at' => $message->created_at,
            ],
        );

        $this->chatRuntimeCache->forgetConversation($chatConversation);
    }
}
