<?php

return [
    'enabled' => env('PUSH_NOTIFICATIONS_ENABLED', false),
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:dhionsetio@gmail.com'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],
    'ttl_seconds' => 300,
    'urgency' => 'normal',
];
