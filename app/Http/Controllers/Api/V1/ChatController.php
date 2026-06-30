<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StartDirectChatRequest;
use App\Http\Requests\StoreChatMessageRequest;
use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\ChatMessage;
use App\Support\ApiRequestContext;
use App\Support\ChatSidebarData;
use App\Support\DirectConversationManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function storeMessage(StoreChatMessageRequest $request, ChatConversation $chatConversation): JsonResponse
    {
        $user = ApiRequestContext::subject($request);
        abort_unless($chatConversation->hasParticipant($user), 403);

        $message = DB::transaction(function () use ($chatConversation, $request, $user): ChatMessage {
            $message = $chatConversation->messages()->create([
                'user_id' => $user->id,
                'body' => $request->body(),
            ]);

            $chatConversation->forceFill([
                'last_message_at' => $message->created_at,
            ])->save();

            ChatConversationParticipant::query()
                ->where('chat_conversation_id', $chatConversation->id)
                ->where('user_id', $user->id)
                ->update(['last_read_at' => $message->created_at]);

            return $message->load('user:id,name,last_name,email,avatar_path,avatar_scale,user_group_id');
        });

        return response()->json([
            'message' => __('ui.chat.message_sent'),
            'data' => [
                'id' => $message->id,
                'body' => $message->body,
                'created_at' => $message->created_at?->toISOString(),
                'is_own' => true,
                'user' => [
                    'id' => $message->user->id,
                    'name' => trim($message->user->name.' '.($message->user->last_name ?? '')),
                    'email' => $message->user->email,
                    'avatar' => $message->user->avatar,
                    'avatar_scale' => $message->user->avatar_scale,
                ],
            ],
        ], 201);
    }
}
