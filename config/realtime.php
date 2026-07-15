<?php

return [

    'notifications' => [
        'queue_connection' => env('NOTIFICATION_QUEUE_CONNECTION', 'redis'),
        'queue' => env('NOTIFICATION_QUEUE', 'notifications'),
        'cache_store' => env('NOTIFICATION_CACHE_STORE', 'redis'),
        'shared_ttl_seconds' => (int) env('NOTIFICATION_SHARED_TTL_SECONDS', 300),
        'mobile_ttl_seconds' => (int) env('NOTIFICATION_MOBILE_TTL_SECONDS', 120),
    ],

    'chats' => [
        'cache_store' => env('CHAT_CACHE_STORE', 'redis'),
        'shared_ttl_seconds' => (int) env('CHAT_SHARED_TTL_SECONDS', 60),
        'sidebar_ttl_seconds' => (int) env('CHAT_SIDEBAR_TTL_SECONDS', 60),
        'unread_ttl_seconds' => (int) env('CHAT_UNREAD_TTL_SECONDS', 60),
    ],

];
