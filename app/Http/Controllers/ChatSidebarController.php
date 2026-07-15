<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartDirectChatRequest;
use App\Models\User;
use App\Support\ChatSidebarData;
use App\Support\DirectConversationManager;
use App\Support\ManagedUserProfileData;
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
            $activeConversation = $chatSidebarData->resolveConversation($user, (int) $conversationId);
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

    public function showUserProfile(Request $request, User $user, ManagedUserProfileData $managedUserProfileData): JsonResponse
    {
        $viewer = $request->user();
        abort_unless($viewer !== null, 401);

        return response()->json([
            'data' => $managedUserProfileData->serialize($user->load('group:id,name')),
            'canEdit' => $managedUserProfileData->canEdit($viewer, $user),
            'managerOptions' => $managedUserProfileData->managerOptions($viewer),
        ]);
    }
}
