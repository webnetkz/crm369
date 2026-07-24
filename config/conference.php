<?php

$stunUrls = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CONFERENCE_STUN_URLS', 'stun:stun.cloudflare.com:3478')),
)));
$turnUrls = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CONFERENCE_TURN_URLS', '')),
)));
$iceServers = [];

if ($stunUrls !== []) {
    $iceServers[] = ['urls' => $stunUrls];
}

if ($turnUrls !== []) {
    $iceServers[] = [
        'urls' => $turnUrls,
        'username' => (string) env('CONFERENCE_TURN_USERNAME', ''),
        'credential' => (string) env('CONFERENCE_TURN_CREDENTIAL', ''),
    ];
}

return [
    'provider_label' => 'CRM369 Local WebRTC',
    'ice_servers' => $iceServers,
    'stun_urls' => $stunUrls,
    'turn_urls' => $turnUrls,
    'turn_username' => (string) env('CONFERENCE_TURN_USERNAME', ''),
    'turn_credential' => (string) env('CONFERENCE_TURN_CREDENTIAL', ''),
    'turn_secret' => (string) env('CONFERENCE_TURN_SECRET', ''),
    'turn_credential_ttl_seconds' => (int) env('CONFERENCE_TURN_CREDENTIAL_TTL_SECONDS', 3600),
    'poll_interval_ms' => 1200,
    'presence_timeout_seconds' => (int) env('CONFERENCE_PRESENCE_TIMEOUT_SECONDS', 120),
    'max_participants' => (int) env('CONFERENCE_MAX_PARTICIPANTS', 12),
    'signal_ttl_seconds' => 120,
    'message_history_limit' => 100,
];
