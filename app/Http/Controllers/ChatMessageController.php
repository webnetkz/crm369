<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatMessageRequest;
use App\Models\ChatConversation;
use App\Models\ChatMessageAttachment;
use App\Support\ChatMessageData;
use App\Support\ChatMessageSender;
use App\Support\TaskConversationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatMessageController extends Controller
{
    public function store(
        StoreChatMessageRequest $request,
        ChatConversation $chatConversation,
        ChatMessageData $chatMessageData,
        ChatMessageSender $chatMessageSender,
        TaskConversationManager $taskConversationManager,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        if ($chatConversation->type === ChatConversation::TYPE_TASK) {
            $chatConversation->loadMissing('task.coAssignees:id');

            $task = $chatConversation->task;

            abort_unless($task !== null && $chatConversation->isAccessibleBy($user), 403);

            $chatConversation = $taskConversationManager->ensureForTask($task, $user)->fresh() ?? $chatConversation;
        } else {
            abort_unless($chatConversation->hasParticipant($user), 403);
        }

        $message = $chatMessageSender->send($chatConversation, $user, $request);

        return response()->json([
            'message' => $chatMessageData->serialize($message, $user),
        ]);
    }

    public function downloadAttachment(ChatMessageAttachment $chatMessageAttachment): StreamedResponse
    {
        $user = request()->user();
        abort_unless($user !== null, 401);

        $chatMessageAttachment->loadMissing('message.conversation.task');

        $conversation = $chatMessageAttachment->message?->conversation;

        abort_unless($conversation !== null && $conversation->isAccessibleBy($user), 403);

        return Storage::disk($chatMessageAttachment->disk)
            ->download($chatMessageAttachment->path, $chatMessageAttachment->original_name);
    }

    public function previewAttachment(
        ChatMessageAttachment $chatMessageAttachment,
        ChatMessageData $chatMessageData,
    ): BinaryFileResponse {
        $user = request()->user();
        abort_unless($user !== null, 401);

        $chatMessageAttachment->loadMissing('message.conversation.task');

        $conversation = $chatMessageAttachment->message?->conversation;

        abort_unless($conversation !== null && $conversation->isAccessibleBy($user), 403);
        abort_unless($chatMessageData->isPreviewableImage($chatMessageAttachment), 404);

        return response()->file(
            Storage::disk($chatMessageAttachment->disk)->path($chatMessageAttachment->path),
            [
                'Content-Type' => $chatMessageAttachment->mime_type ?? 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
