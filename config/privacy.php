<?php

return [
    'operator' => [
        'name' => env('PRIVACY_OPERATOR_NAME', 'Dhion Setio'),
        'email' => env('PRIVACY_CONTACT_EMAIL', 'dhionsetio@gmail.com'),
        'role' => 'Thesis prototype owner and operator',
        'production_controller_established' => env('PRIVACY_CONTROLLER_ESTABLISHED', false),
    ],

    'policy' => [
        'version' => '2026-07-20-prototype.1',
        'effective_date' => '2026-07-20',
        'authoritative_locale' => 'id',
        'review_status' => 'Owner-approved prototype policy; qualified legal review not recorded',
    ],

    'request_due_days' => 30,
    'export_expiry_hours' => 24,
    'request_evidence_retention_days' => 1095,
    'backup_expiry_days' => 30,

    'retention' => [
        'account_identity' => 'While active; pseudonymized after an approved deletion request.',
        'enrollment' => 'While active plus the institution archive period; production policy remains institution-specific.',
        'learning_progress' => 'While the account or institution learning scope is active; handled by the approved request workflow.',
        'open_responses' => 'Only bounded structured responses are stored; free-form production responses are not retained.',
        'administration_audits' => 'Three years in the prototype policy, using pseudonymous actors after deletion.',
        'security_logs' => 'One year in the prototype policy; production log storage is not yet selected.',
        'exports' => '24 hours after generation, then the encrypted artifact is removed.',
        'request_evidence' => 'Three years after closure without retaining the deleted account content.',
        'backups' => 'Up to 30 days; restored accounts remain suppressed by deletion evidence before service resumes.',
    ],
];
