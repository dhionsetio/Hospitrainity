<?php

return [
    'driver' => env('UPLOAD_SCANNER_DRIVER', 'clamav'),
    'required' => env('UPLOAD_SCANNER_REQUIRED', env('APP_ENV', 'production') === 'production'),
    'clamav' => [
        'binary' => env('CLAMAV_BINARY', 'clamscan'),
        'timeout_seconds' => (int) env('CLAMAV_TIMEOUT_SECONDS', 30),
    ],
];
