<?php

namespace App\Support;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ChatRuntimeCache
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function shared(User $user, callable $callback): mixed
    {
        return $this->remember(
            userId: $user->id,
            suffix: 'shared',
            ttlSeconds: (int) config('realtime.chats.shared_ttl_seconds', 60),
            callback: $callback,
        );
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function sidebar(User $user, callable $callback): mixed
    {
        return $this->remember(
            userId: $user->id,
            suffix: 'sidebar',
            ttlSeconds: (int) config('realtime.chats.sidebar_ttl_seconds', 60),
            callback: $callback,
        );
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function unreadConversations(User $user, int $limit, callable $callback): mixed
    {
        return $this->remember(
            userId: $user->id,
            suffix: 'unread-conversations:'.$limit,
            ttlSeconds: (int) config('realtime.chats.unread_ttl_seconds', 60),
            callback: $callback,
        );
    }

    public function forgetUser(User|int $user): void
    {
        $userId = $user instanceof User ? $user->id : $user;

        try {
            $repository = Cache::store($this->storeName());
            $versionKey = $this->versionKey($userId);
            $currentVersion = (int) $repository->get($versionKey, 1);
            $repository->forever($versionKey, $currentVersion + 1);
        } catch (Throwable) {
            // Fall back to fresh database reads when the cache backend is unavailable.
        }
    }

    /**
     * @param  iterable<int, int>  $userIds
     */
    public function forgetUsers(iterable $userIds): void
    {
        foreach ($userIds as $userId) {
            $this->forgetUser($userId);
        }
    }

    public function forgetConversation(ChatConversation $conversation): void
    {
        $conversation->loadMissing('participants:id,chat_conversation_id,user_id');

        $this->forgetUsers(
            $conversation->participants
                ->pluck('user_id')
                ->filter(fn (mixed $userId): bool => is_numeric($userId))
                ->map(fn (mixed $userId): int => (int) $userId)
                ->all(),
        );
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function remember(int $userId, string $suffix, int $ttlSeconds, callable $callback): mixed
    {
        try {
            return Cache::store($this->storeName())->remember(
                $this->cacheKey($userId, $suffix),
                now()->addSeconds($ttlSeconds),
                $callback,
            );
        } catch (Throwable) {
            return $callback();
        }
    }

    private function cacheKey(int $userId, string $suffix): string
    {
        return sprintf(
            'realtime:chats:user:%d:v%d:%s',
            $userId,
            $this->version($userId),
            $suffix,
        );
    }

    private function version(int $userId): int
    {
        try {
            return (int) Cache::store($this->storeName())->rememberForever(
                $this->versionKey($userId),
                fn (): int => 1,
            );
        } catch (Throwable) {
            return 1;
        }
    }

    private function versionKey(int $userId): string
    {
        return 'realtime:chats:user:'.$userId.':version';
    }

    private function storeName(): string
    {
        return (string) config('realtime.chats.cache_store', 'redis');
    }
}
