<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\User;
use App\Support\ChatMessageData;
use App\Support\ChatMessagePinner;
use App\Support\GeneralChatManager;
use App\Support\ManagedUserProfileData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MobileChatController extends Controller
{
    public function pin(
        Request $request,
        ChatConversation $chatConversation,
        ChatMessage $chatMessage,
        ChatMessageData $chatMessageData,
        ChatMessagePinner $chatMessagePinner,
        GeneralChatManager $generalChatManager,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $this->ensureMessageCanBePinned($chatConversation, $chatMessage, $user, $generalChatManager);

        $message = $chatMessagePinner->pin($chatMessage, $user);

        return response()->json([
            'message' => __('ui.chat.message_pinned'),
            'data' => $chatMessageData->serialize($message, $user),
        ]);
    }

    public function unpin(
        Request $request,
        ChatConversation $chatConversation,
        ChatMessage $chatMessage,
        ChatMessageData $chatMessageData,
        ChatMessagePinner $chatMessagePinner,
        GeneralChatManager $generalChatManager,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $this->ensureMessageCanBePinned($chatConversation, $chatMessage, $user, $generalChatManager);

        $message = $chatMessagePinner->unpin($chatMessage);

        return response()->json([
            'message' => __('ui.chat.message_unpinned'),
            'data' => $chatMessageData->serialize($message, $user),
        ]);
    }

    /**
     * Stream an image or audio attachment inline, for conversation participants only.
     */
    public function previewAttachment(
        Request $request,
        ChatMessageAttachment $chatMessageAttachment,
        ChatMessageData $chatMessageData,
    ): BinaryFileResponse {
        $this->ensureAttachmentIsAccessible($request, $chatMessageAttachment);
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

    /**
     * Download an attachment with its original file name, for conversation participants only.
     */
    public function downloadAttachment(Request $request, ChatMessageAttachment $chatMessageAttachment): StreamedResponse
    {
        $this->ensureAttachmentIsAccessible($request, $chatMessageAttachment);

        return Storage::disk($chatMessageAttachment->disk)
            ->download($chatMessageAttachment->path, $chatMessageAttachment->original_name);
    }

    /**
     * The profile card of a portal user opened from a chat (the web chat profile payload).
     */
    public function showUserProfile(Request $request, User $user, ManagedUserProfileData $managedUserProfileData): JsonResponse
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User, 401);

        return response()->json([
            'data' => $managedUserProfileData->serialize($user->load('group:id,name')),
            'canEdit' => $managedUserProfileData->canEdit($viewer, $user),
            'managerOptions' => $managedUserProfileData->managerOptions($viewer),
        ]);
    }

    private function ensureMessageCanBePinned(
        ChatConversation $chatConversation,
        ChatMessage $chatMessage,
        User $user,
        GeneralChatManager $generalChatManager,
    ): void {
        abort_unless($chatMessage->chat_conversation_id === $chatConversation->id, 404);
        abort_unless(! $chatMessage->wasDeleted(), 404);

        if ($chatConversation->type === ChatConversation::TYPE_TASK) {
            $chatConversation->loadMissing('task');
        } else {
            $generalChatManager->ensureParticipant($chatConversation, $user);
        }

        abort_unless($chatConversation->isAccessibleBy($user), 403);
    }

    private function ensureAttachmentIsAccessible(Request $request, ChatMessageAttachment $chatMessageAttachment): void
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $chatMessageAttachment->loadMissing('message.conversation.task');

        $message = $chatMessageAttachment->message;
        $conversation = $message?->conversation;

        abort_unless($conversation !== null && $conversation->isAccessibleBy($user), 403);
        abort_unless(! $message->wasDeleted(), 404);
    }
}
