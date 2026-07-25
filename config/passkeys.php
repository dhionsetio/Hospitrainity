<?php

return [
    'relying_party_id' => env('PASSKEYS_RELYING_PARTY_ID', parse_url(config('app.url'), PHP_URL_HOST)),
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('PASSKEYS_ALLOWED_ORIGINS', config('app.url'))),
    ))),
    'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET', config('app.key')),
    'timeout' => 60_000,
    'guard' => 'web',
    'middleware' => ['web'],
    'management_middleware' => ['password.confirm', 'throttle:passkeys'],
    'throttle' => 'throttle:passkeys',
    'redirect' => '/dashboard',
];
