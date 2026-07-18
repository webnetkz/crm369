<?php

namespace App\Support;

use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\ChatMessage;
use App\Models\ChatMessageRead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ChatConversationReadMarker
{
    public function __construct(
        private readonly ChatRuntimeCache $chatRuntimeCache,
    ) {}

    public function mark(ChatConversation $conversation, User $user): void
    {
        $readAt = now();

        DB::transaction(function () use ($conversation, $readAt, $user): void {
            $conversation->messages()
                ->select('id')
                ->where('user_id', '!=', $user->id)
                ->whereDoesntHave(
                    'reads',
                    fn (Builder $query): Builder => $query->where('user_id', $user->id),
                )
                ->chunkById(500, function (Collection $messages) use ($readAt, $user): void {
                    ChatMessageRead::query()->insertOrIgnore(
                        $messages
                            ->map(fn (ChatMessage $message): array => [
                                'chat_message_id' => $message->id,
                                'user_id' => $user->id,
                                'read_at' => $readAt,
                                'created_at' => $readAt,
                                'updated_at' => $readAt,
                            ])
                            ->all(),
                    );
                });

            ChatConversationParticipant::query()
                ->where('chat_conversation_id', $conversation->id)
                ->where('user_id', $user->id)
                ->update(['last_read_at' => $readAt]);
        });

        $this->chatRuntimeCache->forgetUser($user);
    }
}
