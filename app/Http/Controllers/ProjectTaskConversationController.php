<?php

namespace App\Http\Controllers;

use App\Models\ProjectTask;
use App\Support\ChatSidebarData;
use App\Support\TaskConversationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectTaskConversationController extends Controller
{
    public function show(
        Request $request,
        ProjectTask $projectTask,
        TaskConversationManager $taskConversationManager,
        ChatSidebarData $chatSidebarData,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $task = ProjectTask::query()
            ->visibleTo($user)
            ->with([
                'coAssignees:id,name,last_name,email,avatar_path,avatar_scale,user_group_id',
            ])
            ->findOrFail($projectTask->id);

        $conversation = $taskConversationManager->ensureForTask($task, $user);

        $chatSidebarData->markConversationAsRead($conversation, $user);

        $resolvedConversation = $conversation->fresh([
            'participants.user:id,name,last_name,email,avatar_path,avatar_scale,user_group_id',
            'messages.user:id,name,last_name,email,avatar_path,avatar_scale,user_group_id',
        ]) ?? $conversation;

        return response()->json([
            'conversation' => $chatSidebarData->serializeActiveConversation($resolvedConversation, $user),
        ]);
    }
}
