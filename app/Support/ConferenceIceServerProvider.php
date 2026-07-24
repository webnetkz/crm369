<?php

namespace App\Support;

use App\Models\ConferenceParticipant;

class ConferenceIceServerProvider
{
    /**
     * @return array{
     *     ice_servers: array<int, array<string, array<int, string>|string>>,
     *     ice_servers_expires_at: int|null
     * }
     */
    public function forParticipant(ConferenceParticipant $participant): array
    {
        $iceServers = [];
        $credentialsExpireAt = null;
        $stunUrls = array_values((array) config('conference.stun_urls', []));
        $turnUrls = array_values((array) config('conference.turn_urls', []));

        if ($stunUrls !== []) {
            $iceServers[] = ['urls' => $stunUrls];
        }

        if ($turnUrls === []) {
            return [
                'ice_servers' => $iceServers,
                'ice_servers_expires_at' => null,
            ];
        }

        $turnServer = ['urls' => $turnUrls];
        $turnSecret = (string) config('conference.turn_secret', '');

        if ($turnSecret !== '') {
            $credentialTtl = max(
                60,
                (int) config('conference.turn_credential_ttl_seconds', 3600),
            );
            $refreshWindow = min(300, max(60, intdiv($credentialTtl, 4)));
            $credentialsExpireAt = intdiv(now()->timestamp, $refreshWindow)
                * $refreshWindow
                + $credentialTtl;
            $username = "{$credentialsExpireAt}:conference-{$participant->id}";

            $turnServer['username'] = $username;
            $turnServer['credential'] = base64_encode(
                hash_hmac('sha1', $username, $turnSecret, true),
            );
        } else {
            $username = (string) config('conference.turn_username', '');
            $credential = (string) config('conference.turn_credential', '');

            if ($username !== '' && $credential !== '') {
                $turnServer['username'] = $username;
                $turnServer['credential'] = $credential;
            }
        }

        $iceServers[] = $turnServer;

        return [
            'ice_servers' => $iceServers,
            'ice_servers_expires_at' => $credentialsExpireAt,
        ];
    }
}
