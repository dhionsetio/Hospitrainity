<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Disposable demo identities
    |--------------------------------------------------------------------------
    |
    | DatabaseSeeder is intentionally unavailable unless this flag is enabled
    | in a local or testing process. Production is rejected even if somebody
    | accidentally carries the flag into that environment.
    |
    */
    'demo_seed' => [
        'enabled' => (bool) env('HOSPITRAINITY_DEMO_SEED', false),
        'allowed_environments' => ['local', 'testing'],
        'accounts' => [
            [
                'name' => 'Hospitrainity Test Superadmin',
                'institution_key' => 'hospitrainity-hq',
                'legacy_institution' => 'Hospitrainity HQ',
                'email' => env('HOSPITRAINITY_DEMO_SUPERADMIN_EMAIL', 'superadmin@example.com'),
                'password' => env('HOSPITRAINITY_DEMO_SUPERADMIN_PASSWORD'),
                'role' => 'superadmin',
            ],
            [
                'name' => 'Hospitrainity Test Supervisor',
                'institution_key' => 'demo-hotel-a',
                'legacy_institution' => 'Hotel A',
                'email' => env('HOSPITRAINITY_DEMO_SUPERVISOR_EMAIL', 'supervisor@example.com'),
                'password' => env('HOSPITRAINITY_DEMO_SUPERVISOR_PASSWORD'),
                'role' => 'supervisor',
            ],
            [
                'name' => 'Hospitrainity Test Content Admin',
                'institution_key' => 'demo-hotel-a',
                'legacy_institution' => 'Hotel A',
                'email' => env('HOSPITRAINITY_DEMO_ADMIN_EMAIL', 'admin@example.com'),
                'password' => env('HOSPITRAINITY_DEMO_ADMIN_PASSWORD'),
                'role' => 'admin',
            ],
            [
                'name' => 'Hospitrainity Test Learner',
                'institution_key' => 'demo-hotel-b',
                'legacy_institution' => 'Hotel B',
                'email' => env('HOSPITRAINITY_DEMO_LEARNER_EMAIL', 'user@example.com'),
                'password' => env('HOSPITRAINITY_DEMO_LEARNER_PASSWORD'),
                'role' => 'user',
            ],
        ],
    ],

    'known_demo_emails' => [
        'superadmin@example.com',
        'supervisor@example.com',
        'admin@example.com',
        'user@example.com',
    ],
    'known_demo_institution_keys' => [
        'demo-hotel-a',
        'demo-hotel-b',
    ],

    'invitation' => [
        'ttl_hours' => (int) env('HOSPITRAINITY_INVITATION_TTL_HOURS', 72),
    ],
];
