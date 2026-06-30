<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterNotificationsIndexRequest;
use App\Support\PaginationData;
use App\Support\PerPageOptions;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPageController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(FilterNotificationsIndexRequest $request): Response
    {
        $filters = $request->filters();

        $notifications = $request->user()
            ?->notifications()
            ->when(
                $filters['status'] === 'unread',
                fn ($query) => $query->whereNull('read_at'),
            )
            ->when(
                $filters['status'] === 'read',
                fn ($query) => $query->whereNotNull('read_at'),
            )
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'title' => (string) data_get($notification->data, 'title', __('ui.notifications.default_title')),
                'message' => (string) data_get($notification->data, 'message', ''),
                'actionUrl' => data_get($notification->data, 'action_url'),
                'actionLabel' => data_get($notification->data, 'action_label'),
                'createdAt' => $notification->created_at?->toISOString(),
                'isRead' => $notification->read_at !== null,
            ]);

        return Inertia::render('notifications/Index', [
            'filters' => $filters,
            'notificationFeed' => PaginationData::from($notifications),
            'perPageOptions' => PerPageOptions::allowed(),
        ]);
    }
}
