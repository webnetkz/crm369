<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartDirectChatRequest;
use App\Models\ChatConversation;
use App\Support\ChatSidebarData;
use App\Support\DirectConversationManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatSidebarController extends Controller
{
    public function index(Request $request, ChatSidebarData $chatSidebarData): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'conversation' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        abort_unless($user !== null, 401);

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

    public function startDirect(StartDirectChatRequest $request, DirectConversationManager $directConversationManager): JsonResponse
    {
        $user = $request->user();
        $recipient = $request->recipient();
        abort_unless($user !== null, 401);

        $conversation = $directConversationManager->ensure($user, $recipient);

        return response()->json([
            'conversationId' => $conversation->id,
        ]);
    }
}
