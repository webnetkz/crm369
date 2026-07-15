<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatMessageRequest;
use App\Http\Requests\UpdateChatMessageRequest;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Support\ChatMessageData;
use App\Support\ChatMessageEditor;
use App\Support\ChatMessagePinner;
use App\Support\ChatMessageRemover;
use App\Support\ChatMessageSender;
use App\Support\GeneralChatManager;
use App\Support\TaskConversationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatMessageController extends Controller
{
    public function store(
        StoreChatMessageRequest $request,
        ChatConversation $chatConversation,
        ChatMessageData $chatMessageData,
        ChatMessageSender $chatMessageSender,
        GeneralChatManager $generalChatManager,
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
            $generalChatManager->ensureParticipant($chatConversation, $user);
            abort_unless($chatConversation->isAccessibleBy($user), 403);
        }

        $message = $chatMessageSender->send($chatConversation, $user, $request);

        return response()->json([
            'message' => $chatMessageData->serialize($message, $user),
        ]);
    }

    public function update(
        UpdateChatMessageRequest $request,
        ChatConversation $chatConversation,
        ChatMessage $chatMessage,
        ChatMessageData $chatMessageData,
        ChatMessageEditor $chatMessageEditor,
        GeneralChatManager $generalChatManager,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        abort_unless($chatMessage->chat_conversation_id === $chatConversation->id, 404);
        abort_unless($chatMessage->user_id === $user->id, 403);
        abort_unless(! $chatMessage->wasDeleted(), 404);

        if ($chatConversation->type === ChatConversation::TYPE_TASK) {
            $chatConversation->loadMissing('task');
            abort_unless($chatConversation->isAccessibleBy($user), 403);
        } else {
            $generalChatManager->ensureParticipant($chatConversation, $user);
            abort_unless($chatConversation->isAccessibleBy($user), 403);
        }

        $message = $chatMessageEditor->edit($chatMessage, $request->body());

        return response()->json([
            'message' => $chatMessageData->serialize($message, $user),
        ]);
    }

    public function destroy(
        ChatConversation $chatConversation,
        ChatMessage $chatMessage,
        ChatMessageData $chatMessageData,
        ChatMessageRemover $chatMessageRemover,
        GeneralChatManager $generalChatManager,
    ): JsonResponse {
        $user = request()->user();
        abort_unless($user !== null, 401);

        abort_unless($chatMessage->chat_conversation_id === $chatConversation->id, 404);
        abort_unless($chatMessage->user_id === $user->id, 403);

        if ($chatConversation->type === ChatConversation::TYPE_TASK) {
            $chatConversation->loadMissing('task');
            abort_unless($chatConversation->isAccessibleBy($user), 403);
        } else {
            $generalChatManager->ensureParticipant($chatConversation, $user);
            abort_unless($chatConversation->isAccessibleBy($user), 403);
        }

        $message = $chatMessageRemover->remove($chatMessage);

        return response()->json([
            'message' => $chatMessageData->serialize($message, $user),
        ]);
    }

    public function pin(
        ChatConversation $chatConversation,
        ChatMessage $chatMessage,
        ChatMessageData $chatMessageData,
        ChatMessagePinner $chatMessagePinner,
        GeneralChatManager $generalChatManager,
    ): JsonResponse {
        $user = request()->user();
        abort_unless($user !== null, 401);

        abort_unless($chatMessage->chat_conversation_id === $chatConversation->id, 404);
        abort_unless(! $chatMessage->wasDeleted(), 404);

        if ($chatConversation->type === ChatConversation::TYPE_TASK) {
            $chatConversation->loadMissing('task');
            abort_unless($chatConversation->isAccessibleBy($user), 403);
        } else {
            $generalChatManager->ensureParticipant($chatConversation, $user);
            abort_unless($chatConversation->isAccessibleBy($user), 403);
        }

        $message = $chatMessagePinner->pin($chatMessage, $user);

        return response()->json([
            'message' => $chatMessageData->serialize($message, $user),
        ]);
    }

    public function unpin(
        ChatConversation $chatConversation,
        ChatMessage $chatMessage,
        ChatMessageData $chatMessageData,
        ChatMessagePinner $chatMessagePinner,
        GeneralChatManager $generalChatManager,
    ): JsonResponse {
        $user = request()->user();
        abort_unless($user !== null, 401);

        abort_unless($chatMessage->chat_conversation_id === $chatConversation->id, 404);
        abort_unless(! $chatMessage->wasDeleted(), 404);

        if ($chatConversation->type === ChatConversation::TYPE_TASK) {
            $chatConversation->loadMissing('task');
            abort_unless($chatConversation->isAccessibleBy($user), 403);
        } else {
            $generalChatManager->ensureParticipant($chatConversation, $user);
            abort_unless($chatConversation->isAccessibleBy($user), 403);
        }

        $message = $chatMessagePinner->unpin($chatMessage);

        return response()->json([
            'message' => $chatMessageData->serialize($message, $user),
        ]);
    }

    public function downloadAttachment(ChatMessageAttachment $chatMessageAttachment): StreamedResponse
    {
        $user = request()->user();
        abort_unless($user !== null, 401);

        $chatMessageAttachment->loadMissing('message.conversation.task');

        $message = $chatMessageAttachment->message;
        $conversation = $message?->conversation;

        abort_unless($conversation !== null && $conversation->isAccessibleBy($user), 403);
        abort_unless($message !== null && ! $message->wasDeleted(), 404);

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

        $message = $chatMessageAttachment->message;
        $conversation = $message?->conversation;

        abort_unless($conversation !== null && $conversation->isAccessibleBy($user), 403);
        abort_unless($message !== null && ! $message->wasDeleted(), 404);
        abort_unless($chatMessageData->isInlinePreviewable($chatMessageAttachment), 404);

        $response = response()->file(
            Storage::disk($chatMessageAttachment->disk)->path($chatMessageAttachment->path),
            [
                'Content-Type' => $chatMessageAttachment->mime_type ?? 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $chatMessageAttachment->original_name,
        );

        return $response;
    }
}
