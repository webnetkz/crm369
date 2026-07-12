<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StartDirectChatRequest;
use App\Http\Requests\StoreChatMessageRequest;
use App\Http\Requests\UpdateChatMessageRequest;
use App\Http\Resources\ApiChatMessageResource;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Support\ApiRequestContext;
use App\Support\ChatMessageEditor;
use App\Support\ChatMessageRemover;
use App\Support\ChatMessageSender;
use App\Support\ChatSidebarData;
use App\Support\DirectConversationManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController
{
    public function index(Request $request, ChatSidebarData $chatSidebarData): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'conversation' => ['nullable', 'integer'],
        ]);

        $user = ApiRequestContext::subject($request);
        $activeConversation = null;
        $conversationId = $validated['conversation'] ?? null;

        if (is_numeric($conversationId)) {
            $activeConversation = ChatConversation::query()
                ->with('participants.user:id,name,last_name,email,avatar_path,avatar_scale,user_group_id')
                ->whereKey((int) $conversationId)
                ->whereHas('participants', fn (Builder $query) => $query->where('user_id', $user->id))
                ->firstOrFail();

            $chatSidebarData->markConversationAsRead($activeConversation, $user);
        }

        return response()->json(
            $chatSidebarData->build(
                $user,
                (string) ($validated['search'] ?? ''),
                $activeConversation,
            ),
        );
    }

    public function storeDirect(StartDirectChatRequest $request, DirectConversationManager $directConversationManager): JsonResponse
    {
        $user = ApiRequestContext::subject($request);
        $recipient = $request->recipient();
        $conversation = $directConversationManager->ensure($user, $recipient);

        return response()->json([
            'message' => __('ui.chat.direct_chat_ready'),
            'data' => [
                'conversation_id' => $conversation->id,
            ],
        ], 201);
    }

    public function storeMessage(
        StoreChatMessageRequest $request,
        ChatConversation $chatConversation,
        ChatMessageSender $chatMessageSender,
    ): JsonResponse {
        $user = ApiRequestContext::subject($request);
        abort_unless($chatConversation->hasParticipant($user), 403);

        $message = $chatMessageSender->send($chatConversation, $user, $request);

        return response()->json([
            'message' => __('ui.chat.message_sent'),
            'data' => (new ApiChatMessageResource($message))->resolve(),
        ], 201);
    }

    public function updateMessage(
        UpdateChatMessageRequest $request,
        ChatConversation $chatConversation,
        ChatMessage $chatMessage,
        ChatMessageEditor $chatMessageEditor,
    ): JsonResponse {
        $user = ApiRequestContext::subject($request);

        abort_unless($chatMessage->chat_conversation_id === $chatConversation->id, 404);
        abort_unless($chatConversation->hasParticipant($user), 403);
        abort_unless($chatMessage->user_id === $user->id, 403);
        abort_unless(! $chatMessage->wasDeleted(), 404);

        $message = $chatMessageEditor->edit($chatMessage, $request->body());

        return response()->json([
            'message' => __('ui.chat.message_sent'),
            'data' => (new ApiChatMessageResource($message))->resolve(),
        ]);
    }

    public function destroyMessage(
        Request $request,
        ChatConversation $chatConversation,
        ChatMessage $chatMessage,
        ChatMessageRemover $chatMessageRemover,
    ): JsonResponse {
        $user = ApiRequestContext::subject($request);

        abort_unless($chatMessage->chat_conversation_id === $chatConversation->id, 404);
        abort_unless($chatConversation->hasParticipant($user), 403);
        abort_unless($chatMessage->user_id === $user->id, 403);

        $message = $chatMessageRemover->remove($chatMessage);

        return response()->json([
            'message' => __('ui.chat.message_sent'),
            'data' => (new ApiChatMessageResource($message))->resolve(),
        ]);
    }
}
