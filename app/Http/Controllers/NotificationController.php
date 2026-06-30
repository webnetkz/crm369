<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function update(Request $request, string $notification): RedirectResponse
    {
        $databaseNotification = $request->user()?->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        if ($databaseNotification->read_at === null) {
            $databaseNotification->markAsRead();
        }

        return back();
    }

    public function updateAll(Request $request): RedirectResponse
    {
        $request->user()?->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.notifications.all_marked_as_read')]);

        return back();
    }
}
