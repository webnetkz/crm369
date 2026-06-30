<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChatMessageRequest;
use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\ChatMessage;
use App\Support\TaskConversationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ChatMessageController extends Controller
{
    public function store(
        StoreChatMessageRequest $request,
        ChatConversation $chatConversation,
        TaskConversationManager $taskConversationManager,
    ): JsonResponse
    {
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
            'message' => [
                'id' => $message->id,
                'body' => $message->body,
                'createdAt' => $message->created_at?->toISOString(),
                'isOwn' => true,
                'user' => [
                    'id' => $message->user->id,
                    'name' => trim($message->user->name.' '.($message->user->last_name ?? '')),
                    'email' => $message->user->email,
                    'avatar' => $message->user->avatar,
                    'avatarScale' => $message->user->avatar_scale,
                ],
            ],
        ]);
    }
}
