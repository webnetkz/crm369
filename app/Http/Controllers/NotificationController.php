<?php

namespace App\Http\Controllers;

use App\Support\NotificationRuntimeCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function update(
        Request $request,
        string $notification,
        NotificationRuntimeCache $notificationRuntimeCache,
    ): RedirectResponse {
        $user = $request->user();
        $databaseNotification = $user?->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        if ($databaseNotification->read_at === null) {
            $databaseNotification->markAsRead();
        }

        if ($user !== null) {
            $notificationRuntimeCache->forget($user);
        }

        return back();
    }

    public function updateAll(Request $request, NotificationRuntimeCache $notificationRuntimeCache): RedirectResponse
    {
        $user = $request->user();

        $user?->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        if ($user !== null) {
            $notificationRuntimeCache->forget($user);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.notifications.all_marked_as_read')]);

        return back();
    }
}
