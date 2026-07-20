<?php

return [
    'password' => [
        'minimum' => (int) env('AUTH_PASSWORD_MINIMUM', 15),
        'maximum' => (int) env('AUTH_PASSWORD_MAXIMUM', 128),
    ],
    'mfa' => [
        'step_up_seconds' => (int) env('AUTH_MFA_STEP_UP_SECONDS', 900),
        'recovery_code_count' => 10,
    ],
    'session' => [
        'user_agent_display_max' => 120,
    ],
];
