<?php

namespace App\Support;

use App\Models\MobileDevice;
use Illuminate\Support\Facades\Http;

class FirebaseCloudMessagingClient
{
    public function __construct(private readonly FirebaseAccessTokenProvider $accessTokenProvider) {}

    /**
     * @param  array{title: string, body: string, type: string, action_path?: string|null, entity_id?: string|int|null}  $message
     */
    public function send(MobileDevice $device, array $message): bool
    {
        if (! $this->accessTokenProvider->isConfigured()) {
            return false;
        }

        $projectId = $this->accessTokenProvider->projectId();
        $data = collect([
            'type' => $message['type'],
            'action_path' => $message['action_path'] ?? '',
            'entity_id' => $message['entity_id'] ?? '',
        ])->map(fn (mixed $value): string => (string) $value)->all();

        $response = Http::withToken($this->accessTokenProvider->accessToken())
            ->acceptJson()
            ->connectTimeout((int) config('services.fcm.connect_timeout_seconds', 5))
            ->timeout((int) config('services.fcm.timeout_seconds', 10))
            ->retry(2, 250, throw: false)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $device->fcm_token,
                    'notification' => [
                        'title' => $message['title'],
                        'body' => $message['body'],
                    ],
                    'data' => $data,
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => [
                            'channel_id' => 'crm369_updates',
                            'icon' => 'ic_notification_small',
                            'color' => '#0A6C74',
                            'click_action' => 'OPEN_CRM369',
                        ],
                    ],
                ],
            ]);

        if ($response->successful()) {
            $device->forceFill(['last_seen_at' => now()])->save();

            return true;
        }

        if ($this->isUnregisteredResponse($response->json())) {
            $device->disable();

            return false;
        }

        $response->throw();

        return false;
    }

    private function isUnregisteredResponse(mixed $payload): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        $details = data_get($payload, 'error.details', []);

        if (! is_array($details)) {
            return false;
        }

        foreach ($details as $detail) {
            if (is_array($detail) && ($detail['errorCode'] ?? null) === 'UNREGISTERED') {
                return true;
            }
        }

        return false;
    }
}
