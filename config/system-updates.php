<?php

return [
    'repository' => env('SYSTEM_UPDATES_REPOSITORY', 'webnetkz/crm369'),
    'branch' => env('SYSTEM_UPDATES_BRANCH', 'main'),
    'github_token' => env('SYSTEM_UPDATES_GITHUB_TOKEN'),
    'github_api_url' => env('SYSTEM_UPDATES_GITHUB_API_URL', 'https://api.github.com'),
    'connect_timeout_seconds' => (int) env('SYSTEM_UPDATES_CONNECT_TIMEOUT_SECONDS', 3),
    'timeout_seconds' => (int) env('SYSTEM_UPDATES_TIMEOUT_SECONDS', 10),
    'bridge_path' => env('SYSTEM_UPDATES_BRIDGE_PATH', '/usr/local/sbin/crm369-updater'),
    'version_state_path' => env('SYSTEM_UPDATES_VERSION_STATE_PATH', '/etc/crm369/version.json'),
    'progress_directory' => env('SYSTEM_UPDATES_PROGRESS_DIRECTORY', '/var/lib/crm369/updates'),
    'run_timeout_minutes' => (int) env('SYSTEM_UPDATES_RUN_TIMEOUT_MINUTES', 60),
];
