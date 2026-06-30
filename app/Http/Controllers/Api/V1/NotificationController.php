<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\ApiRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController
{
    public function index(Request $request): JsonResponse
    {
        $user = ApiRequestContext::subject($request);
        $notifications = $user->notifications()
            ->latest()
            ->paginate((int) min(max($request->integer('per_page', 20), 1), 100));

        return response()->json([
            'data' => collect($notifications->items())
                ->map(fn (DatabaseNotification $notification): array => $this->serializeNotification($notification))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    public function update(Request $request, string $notification): JsonResponse
    {
        $user = ApiRequestContext::subject($request);
        $databaseNotification = $user->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        if ($databaseNotification->read_at === null) {
            $databaseNotification->markAsRead();
        }

        return response()->json([
            'message' => __('ui.notifications.marked_as_read'),
            'data' => $this->serializeNotification($databaseNotification->fresh()),
            'meta' => [
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    public function updateAll(Request $request): JsonResponse
    {
        $user = ApiRequestContext::subject($request);

        $user->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        return response()->json([
            'message' => __('ui.notifications.all_marked_as_read'),
            'meta' => [
                'unread_count' => 0,
            ],
        ]);
    }

    private function serializeNotification(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => (string) data_get($notification->data, 'title', __('ui.notifications.default_title')),
            'message' => (string) data_get($notification->data, 'message', ''),
            'action_url' => data_get($notification->data, 'action_url'),
            'action_label' => data_get($notification->data, 'action_label'),
            'created_at' => $notification->created_at?->toISOString(),
            'read_at' => $notification->read_at?->toISOString(),
            'is_read' => $notification->read_at !== null,
        ];
    }
}
