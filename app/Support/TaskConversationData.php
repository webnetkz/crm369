<?php

namespace App\Support;

use App\Models\ProjectTask;
use App\Models\User;

class TaskConversationData
{
    public function __construct(
        private TaskConversationManager $taskConversationManager,
        private ChatSidebarData $chatSidebarData,
    ) {}

    /**
     * Open the discussion of a task visible to the viewer. The conversation is created on first access,
     * its participants are synchronized with the task, and its messages are marked as read by the viewer.
     *
     * @return array<string, mixed>
     */
    public function open(ProjectTask $projectTask, User $viewer): array
    {
        $task = ProjectTask::query()
            ->visibleTo($viewer)
            ->with([
                'coAssignees:id,name,last_name,email,avatar_path,avatar_scale,user_group_id',
            ])
            ->findOrFail($projectTask->id);

        $conversation = $this->taskConversationManager->ensureForTask($task, $viewer);

        $this->chatSidebarData->markConversationAsRead($conversation, $viewer);

        $resolvedConversation = $conversation->fresh([
            'participants.user:id,name,last_name,email,avatar_path,avatar_scale,user_group_id',
            'messages.user:id,name,last_name,email,avatar_path,avatar_scale,user_group_id',
        ]) ?? $conversation;

        return $this->chatSidebarData->serializeActiveConversation($resolvedConversation, $viewer);
    }
}
