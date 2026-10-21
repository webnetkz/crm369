<?php

namespace App\Support;

use App\Models\PortalSetting;
use Throwable;

class MobilePortalData
{
    public const int API_VERSION = 1;

    public function __construct(private readonly FirebaseAccessTokenProvider $firebaseAccessTokenProvider) {}

    /**
     * Public portal discovery payload used by the native application when a
     * user binds the application to a portal domain.
     *
     * @return array{
     *     product: string,
     *     api_version: int,
     *     name: string,
     *     url: string,
     *     logo_url: string,
     *     default_language: string,
     *     languages: array<int, string>,
     *     web_session: bool,
     *     push: array{enabled: bool, provider: string, firebase: array{project_id: string, application_id: string, api_key: string, sender_id: string}|null}
     * }
     */
    public function build(): array
    {
        $portal = PortalSetting::current();
        $firebase = $this->firebaseClientOptions();

        return [
            'product' => 'crm369',
            'api_version' => self::API_VERSION,
            'name' => $portal->companyName(),
            'url' => rtrim((string) config('app.url'), '/'),
            'logo_url' => url($portal->logoUrl()),
            'default_language' => $portal->defaultLanguage(),
            'languages' => PortalSetting::SUPPORTED_LANGUAGES,
            'web_session' => true,
            'push' => [
                'enabled' => $firebase !== null,
                'provider' => 'fcm',
                'firebase' => $firebase,
            ],
        ];
    }

    /**
     * Firebase client options for the Android application. They are public
     * client identifiers (the same values are embedded into any Android
     * application that uses Firebase) and are only exposed when server side
     * delivery is configured as well.
     *
     * @return array{project_id: string, application_id: string, api_key: string, sender_id: string}|null
     */
    public function firebaseClientOptions(): ?array
    {
        $applicationId = trim((string) config('services.fcm.android.application_id'));
        $apiKey = trim((string) config('services.fcm.android.api_key'));
        $senderId = trim((string) config('services.fcm.android.sender_id'));

        if ($applicationId === '' || $apiKey === '' || $senderId === '') {
            return null;
        }

        if (! $this->firebaseAccessTokenProvider->isConfigured()) {
            return null;
        }

        try {
            $projectId = $this->firebaseAccessTokenProvider->projectId();
        } catch (Throwable) {
            return null;
        }

        return [
            'project_id' => $projectId,
            'application_id' => $applicationId,
            'api_key' => $apiKey,
            'sender_id' => $senderId,
        ];
    }
}
