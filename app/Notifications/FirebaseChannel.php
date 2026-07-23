<?php

namespace App\Notifications;

use App\Models\MobileDevice;
use App\Models\User;
use App\Support\FirebaseCloudMessagingClient;
use Illuminate\Notifications\Notification;

class FirebaseChannel
{
    public function __construct(private readonly FirebaseCloudMessagingClient $client) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toFirebase')) {
            return;
        }

        $message = $notification->toFirebase($notifiable);

        $notifiable->mobileDevices()
            ->get()
            ->each(function (MobileDevice $device) use ($message): void {
                $this->client->send($device, $message);
            });
    }
}
