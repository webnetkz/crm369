<?php

namespace App\Support;

use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TaskConversationManager
{
    public function ensureForTask(ProjectTask $task, ?User $viewer = null): ChatConversation
    {
        $task->loadMissing([
            'coAssignees:id',
        ]);

        return DB::transaction(function () use ($task, $viewer): ChatConversation {
            $conversation = ChatConversation::query()->firstOrCreate(
                [
                    'project_task_id' => $task->id,
                ],
                [
                    'type' => ChatConversation::TYPE_TASK,
                    'title' => $task->title,
                    'created_by_user_id' => $task->creator_user_id,
                ],
            );

            $conversation->forceFill([
                'type' => ChatConversation::TYPE_TASK,
                'title' => $task->title,
                'created_by_user_id' => $task->creator_user_id,
            ])->save();

            $participantIds = collect([
                $task->creator_user_id,
                $task->assignee_user_id,
                $viewer?->id,
                ...$task->coAssignees->pluck('id')->all(),
            ])
                ->filter(fn (?int $userId): bool => is_int($userId) && $userId > 0)
                ->unique()
                ->values()
                ->all();

            ChatConversationParticipant::query()
                ->where('chat_conversation_id', $conversation->id)
                ->whereNotIn('user_id', $participantIds)
                ->delete();

            $existingParticipantIds = ChatConversationParticipant::query()
                ->where('chat_conversation_id', $conversation->id)
                ->pluck('user_id')
                ->map(fn (mixed $userId): int => (int) $userId)
                ->all();

            foreach (array_diff($participantIds, $existingParticipantIds) as $participantId) {
                ChatConversationParticipant::query()->create([
                    'chat_conversation_id' => $conversation->id,
                    'user_id' => $participantId,
                ]);
            }

            return $conversation;
        });
    }
}
