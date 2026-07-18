<?php

namespace App\Support;

use App\Models\ChatConversation;
use App\Models\ChatConversationParticipant;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

class ChatSidebarData
{
    public function __construct(
        private readonly ChatMessageData $chatMessageData,
        private readonly ChatConversationReadMarker $chatConversationReadMarker,
        private readonly ChatRuntimeCache $chatRuntimeCache,
        private readonly GeneralChatManager $generalChatManager,
    ) {}

    /**
     * @return array{unreadCount: int, conversations: array<int, array<string, mixed>>, contacts: array<int, array<string, mixed>>, activeConversation: array<string, mixed>|null}
     */
    public function build(User $user, string $search = '', ?ChatConversation $activeConversation = null): array
    {
        $this->generalChatManager->ensureForUser($user);

        if ($search === '' && $activeConversation === null) {
            return $this->chatRuntimeCache->sidebar(
                $user,
                fn (): array => $this->buildPayload($user, $search, $activeConversation),
            );
        }

        return $this->buildPayload($user, $search, $activeConversation);
    }

    /**
     * @return array{unreadCount: int}
     */
    public function shared(User $user): array
    {
        $this->generalChatManager->ensureForUser($user);

        return $this->chatRuntimeCache->shared($user, fn (): array => [
            'unreadCount' => (int) array_sum($this->unreadCounts($user)),
        ]);
    }

    /**
     * @return array<int, array{id: int, title: string, excerpt: string|null, unreadCount: int, latestMessageId: int|null, lastMessageAt: string|null}>
     */
    public function unreadConversations(User $user, int $limit = 10): array
    {
        $this->generalChatManager->ensureForUser($user);

        return $this->chatRuntimeCache->unreadConversations($user, $limit, function () use ($limit, $user): array {
            $unreadCounts = $this->unreadCounts($user);

            return $this->conversationCollection($user, '')
                ->map(function (ChatConversation $conversation) use ($unreadCounts, $user): array {
                    $otherParticipant = $this->otherParticipant($conversation, $user);

                    return [
                        'id' => $conversation->id,
                        'title' => $conversation->type === ChatConversation::TYPE_DIRECT
                            ? $this->displayName($otherParticipant)
                            : (
                                $conversation->isGeneralConversation()
                                    ? ChatConversation::GENERAL_CHAT_TITLE
                                    : ($conversation->title ?? __('ui.chat.untitled_chat'))
                            ),
                        'excerpt' => $this->chatMessageData->excerpt($conversation->latestMessage),
                        'unreadCount' => Arr::get($unreadCounts, $conversation->id, 0),
                        'latestMessageId' => $conversation->latestMessage?->id,
                        'lastMessageAt' => $conversation->last_message_at?->toISOString(),
                    ];
                })
                ->filter(fn (array $conversation): bool => $conversation['unreadCount'] > 0)
                ->take($limit)
                ->values()
                ->all();
        });
    }

    public function resolveConversation(User $user, int $conversationId): ChatConversation
    {
        $conversation = ChatConversation::query()
            ->whereKey($conversationId)
            ->firstOrFail();

        if ($conversation->isGeneralConversation()) {
            $this->generalChatManager->ensureParticipant($conversation, $user);
        }

        abort_unless($conversation->isAccessibleBy($user), 404);

        return $conversation->fresh([
            'participants.user:id,name,last_name,email,phone,avatar_path,avatar_scale,user_group_id',
            'task',
        ]) ?? $conversation;
    }

    public function markConversationAsRead(ChatConversation $conversation, User $user): void
    {
        if ($conversation->isGeneralConversation()) {
            $this->generalChatManager->ensureParticipant($conversation, $user);
        }

        $this->chatConversationReadMarker->mark($conversation, $user);
    }

    /**
     * @return array{unreadCount: int, conversations: array<int, array<string, mixed>>, contacts: array<int, array<string, mixed>>, activeConversation: array<string, mixed>|null}
     */
    private function buildPayload(User $user, string $search = '', ?ChatConversation $activeConversation = null): array
    {
        $conversations = $this->conversationCollection($user, $search);
        $unreadCounts = $this->unreadCounts($user);
        $resolvedActiveConversation = $activeConversation?->fresh([
            'participants.user:id,name,last_name,email,phone,avatar_path,avatar_scale,user_group_id',
            'messages.user:id,name,last_name,email,phone,avatar_path,avatar_scale,user_group_id',
        ]) ?? $activeConversation;

        return [
            'unreadCount' => (int) array_sum($unreadCounts),
            'conversations' => $conversations
                ->map(fn (ChatConversation $conversation): array => $this->serializeConversationListItem($conversation, $user, $unreadCounts))
                ->values()
                ->all(),
            'contacts' => $this->contactRows($user, $search),
            'activeConversation' => $resolvedActiveConversation
                ? $this->serializeActiveConversation($resolvedActiveConversation, $user)
                : null,
        ];
    }

    /**
     * @return Collection<int, ChatConversation>
     */
    private function conversationCollection(User $user, string $search): Collection
    {
        $generalConversation = $this->generalChatManager
            ->ensureForUser($user)
            ->load([
                'participants.user:id,name,last_name,email,phone,avatar_path,avatar_scale,user_group_id',
                'latestMessage.user:id,name,last_name,email,phone,avatar_path,avatar_scale,user_group_id',
                'latestMessage.attachments',
            ]);

        $conversations = ChatConversation::query()
            ->where('type', ChatConversation::TYPE_DIRECT)
            ->whereHas('participants', fn ($query) => $query->where('user_id', $user->id))
            ->with([
                'participants.user:id,name,last_name,email,phone,avatar_path,avatar_scale,user_group_id',
                'latestMessage.user:id,name,last_name,email,phone,avatar_path,avatar_scale,user_group_id',
                'latestMessage.attachments',
            ])
            ->when($search !== '', function ($query) use ($search, $user): void {
                $query->where(function ($conversationQuery) use ($search, $user): void {
                    $conversationQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhereHas('participants.user', function ($participantQuery) use ($search, $user): void {
                            $participantQuery
                                ->where('users.id', '!=', $user->id)
                                ->where(function ($userQuery) use ($search): void {
                                    $userQuery
                                        ->where('name', 'like', "%{$search}%")
                                        ->orWhere('last_name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(24)
            ->get();

        return new Collection([$generalConversation, ...$conversations->all()]);
    }

    /**
     * @return array<int, int>
     */
    private function unreadCounts(User $user): array
    {
        return ChatMessage::query()
            ->selectRaw('chat_messages.chat_conversation_id, count(*) as aggregate')
            ->join('chat_conversation_participants as participants', function ($join) use ($user): void {
                $join->on('participants.chat_conversation_id', '=', 'chat_messages.chat_conversation_id')
                    ->where('participants.user_id', '=', $user->id);
            })
            ->join('chat_conversations', function ($join): void {
                $join->on('chat_conversations.id', '=', 'chat_messages.chat_conversation_id')
                    ->whereIn('chat_conversations.type', [
                        ChatConversation::TYPE_DIRECT,
                        ChatConversation::TYPE_GENERAL,
                    ]);
            })
            ->where('chat_messages.user_id', '!=', $user->id)
            ->where(function ($query): void {
                $query
                    ->whereNull('participants.last_read_at')
                    ->orWhereColumn('chat_messages.created_at', '>', 'participants.last_read_at');
            })
            ->groupBy('chat_messages.chat_conversation_id')
            ->pluck('aggregate', 'chat_messages.chat_conversation_id')
            ->map(fn (mixed $value): int => (int) $value)
            ->all();
    }

    /**
     * @param  array<int, int>  $unreadCounts
     * @return array<string, mixed>
     */
    private function serializeConversationListItem(ChatConversation $conversation, User $user, array $unreadCounts): array
    {
        $otherParticipant = $this->otherParticipant($conversation, $user);
        $latestMessage = $conversation->latestMessage;

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'title' => $conversation->type === ChatConversation::TYPE_DIRECT
                ? $this->displayName($otherParticipant)
                : (
                    $conversation->isGeneralConversation()
                        ? ChatConversation::GENERAL_CHAT_TITLE
                        : ($conversation->title ?? __('ui.chat.untitled_chat'))
                ),
            'subtitle' => $conversation->type === ChatConversation::TYPE_DIRECT
                ? $otherParticipant?->email
                : null,
            'excerpt' => $this->chatMessageData->excerpt($latestMessage),
            'lastMessageAt' => $conversation->last_message_at?->toISOString(),
            'unreadCount' => Arr::get($unreadCounts, $conversation->id, 0),
            'participant' => $otherParticipant ? $this->serializeUserSummary($otherParticipant) : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function contactRows(User $user, string $search): array
    {
        return User::query()
            ->select(['id', 'name', 'last_name', 'email', 'phone', 'avatar_path', 'avatar_scale', 'is_active'])
            ->where('id', '!=', $user->id)
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($userQuery) use ($search): void {
                    $userQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit($search === '' ? 12 : 20)
            ->get()
            ->map(fn (User $contact): array => $this->serializeUserSummary($contact))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeActiveConversation(ChatConversation $conversation, User $user): array
    {
        $otherParticipant = $this->otherParticipant($conversation, $user);
        $messages = $conversation->messages()
            ->with([
                'user:id,name,last_name,email,phone,avatar_path,avatar_scale,user_group_id',
                'attachments',
                'reads.user:id,name,last_name,email',
            ])
            ->oldest('id')
            ->get()
            ->values()
            ->map(fn (ChatMessage $message): array => $this->chatMessageData->serialize($message, $user))
            ->all();
        $pinnedMessages = collect($messages)
            ->filter(fn (array $message): bool => $message['isPinned'] === true)
            ->sortByDesc('pinnedAt')
            ->values()
            ->all();

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'title' => $conversation->type === ChatConversation::TYPE_DIRECT
                ? $this->displayName($otherParticipant)
                : (
                    $conversation->isGeneralConversation()
                        ? ChatConversation::GENERAL_CHAT_TITLE
                        : ($conversation->title ?? __('ui.chat.untitled_chat'))
                ),
            'subtitle' => $conversation->type === ChatConversation::TYPE_DIRECT
                ? $otherParticipant?->email
                : null,
            'participant' => $otherParticipant ? $this->serializeUserSummary($otherParticipant) : null,
            'participants' => $conversation->participants
                ->map(fn (ChatConversationParticipant $participant): array => $this->serializeUserSummary($participant->user))
                ->values()
                ->all(),
            'messages' => $messages,
            'pinnedMessages' => $pinnedMessages,
        ];
    }

    private function otherParticipant(ChatConversation $conversation, User $user): ?User
    {
        return $conversation->participants
            ->first(fn (ChatConversationParticipant $participant): bool => $participant->user_id !== $user->id)
            ?->user;
    }

    /**
     * @return array{id: int, name: string, email: string, phone: string|null, avatar: string|null, avatarScale: float}
     */
    private function serializeUserSummary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $this->displayName($user),
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'avatarScale' => $user->avatar_scale,
        ];
    }

    private function displayName(?User $user): string
    {
        if (! $user) {
            return __('ui.chat.unknown_user');
        }

        $fullName = trim($user->name.' '.($user->last_name ?? ''));

        return $fullName !== '' ? $fullName : $user->email;
    }
}
