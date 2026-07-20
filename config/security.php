<?php

return [
    'headers_enabled' => env('SECURITY_HEADERS_ENABLED', true),

    'csp' => [
        'enabled' => env('SECURITY_CSP_ENABLED', true),
        'report_only' => env(
            'SECURITY_CSP_REPORT_ONLY',
            env('APP_ENV', 'production') !== 'production',
        ),
        'report_uri' => env('SECURITY_CSP_REPORT_URI', '/security/csp-reports'),
    ],

    'hsts' => [
        'enabled' => env('SECURITY_HSTS_ENABLED', false),
        'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31_536_000),
        'include_subdomains' => env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', false),
        'preload' => env('SECURITY_HSTS_PRELOAD', false),
    ],
];
