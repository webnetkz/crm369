<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\NotificationRuntimeCache;
use Illuminate\Notifications\Events\NotificationSent;

class InvalidateNotificationRuntimeCache
{
    public function __construct(
        private readonly NotificationRuntimeCache $notificationRuntimeCache,
    ) {}

    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database') {
            return;
        }

        if (! $event->notifiable instanceof User) {
            return;
        }

        $this->notificationRuntimeCache->forget($event->notifiable);
    }
}
