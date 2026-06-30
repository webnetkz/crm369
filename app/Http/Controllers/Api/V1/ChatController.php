<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StartDirectChatRequest;
use App\Http\Requests\StoreChatMessageRequest;
use App\Http\Resources\ApiChatMessageResource;
use App\Models\ChatConversation;
use App\Support\ApiRequestContext;
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
}
