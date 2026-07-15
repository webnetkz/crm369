<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class NotificationRuntimeCache
{
    /**
     * @return array{unreadCount: int, items: array<int, array{id: string, title: string, message: string, actionUrl: string|null, actionLabel: string|null, createdAt: string|null, isRead: bool}>}
     */
    public function shared(User $user): array
    {
        if (! Schema::hasTable('notifications')) {
            return [
                'unreadCount' => 0,
                'items' => [],
            ];
        }

        return $this->remember(
            userId: $user->id,
            suffix: 'shared',
            ttlSeconds: (int) config('realtime.notifications.shared_ttl_seconds', 300),
            callback: fn (): array => [
                'unreadCount' => $user->unreadNotifications()->count(),
                'items' => $user->notifications()
                    ->limit(20)
                    ->get()
                    ->map(fn (DatabaseNotification $notification): array => $this->serializeSharedNotification($notification))
                    ->values()
                    ->all(),
            ],
        );
    }

    /**
     * @return array<int, array{key: string, id: string, title: string, message: string, action_path: string|null, created_at: string|null}>
     */
    public function mobileUnread(User $user, int $limit = 10): array
    {
        if (! Schema::hasTable('notifications')) {
            return [];
        }

        return $this->remember(
            userId: $user->id,
            suffix: 'mobile:'.$limit,
            ttlSeconds: (int) config('realtime.notifications.mobile_ttl_seconds', 120),
            callback: fn (): array => $user->unreadNotifications()
                ->latest()
                ->limit($limit)
                ->get()
                ->map(fn (DatabaseNotification $notification): array => [
                    'key' => 'notification:'.$notification->id,
                    'id' => $notification->id,
                    'title' => (string) data_get($notification->data, 'title', __('ui.notifications.default_title')),
                    'message' => (string) data_get($notification->data, 'message', ''),
                    'action_path' => $this->normalizeActionPath(data_get($notification->data, 'action_url')),
                    'created_at' => $notification->created_at?->toISOString(),
                ])
                ->values()
                ->all(),
        );
    }

    public function forget(User|int $user): void
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
            'realtime:notifications:user:%d:v%d:%s',
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
        return 'realtime:notifications:user:'.$userId.':version';
    }

    private function storeName(): string
    {
        return (string) config('realtime.notifications.cache_store', 'redis');
    }

    /**
     * @return array{id: string, title: string, message: string, actionUrl: string|null, actionLabel: string|null, createdAt: string|null, isRead: bool}
     */
    private function serializeSharedNotification(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => (string) data_get($notification->data, 'title', __('ui.notifications.default_title')),
            'message' => (string) data_get($notification->data, 'message', ''),
            'actionUrl' => data_get($notification->data, 'action_url'),
            'actionLabel' => data_get($notification->data, 'action_label'),
            'createdAt' => $notification->created_at?->toISOString(),
            'isRead' => $notification->read_at !== null,
        ];
    }

    private function normalizeActionPath(mixed $actionUrl): ?string
    {
        if (! is_string($actionUrl)) {
            return null;
        }

        $trimmedActionUrl = trim($actionUrl);

        if ($trimmedActionUrl === '') {
            return null;
        }

        if (str_starts_with($trimmedActionUrl, '/')) {
            return $trimmedActionUrl;
        }

        $parts = parse_url($trimmedActionUrl);

        if ($parts === false) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $path.$query.$fragment;
    }
}
