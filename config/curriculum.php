<?php

return [
    'authoring_schema_version' => '2.1.0',
    'package_path' => base_path('curriculum/hospitrainity/0.4.0-draft'),
    'evidence_path' => base_path('curriculum/evidence/cf-7.json'),
    'standalone_template' => resource_path('standalone/hospitrainity-template.html'),
    'standalone_output' => env('CURRICULUM_STANDALONE_OUTPUT', base_path('standalone/Hospitrainity-Standalone.html')),
    'report_directory' => env('CURRICULUM_REPORT_DIRECTORY', storage_path('app/private/curriculum/reports')),
    'rollback_directory' => env('CURRICULUM_ROLLBACK_DIRECTORY', storage_path('app/private/curriculum/rollbacks')),
    'draft_validation_directory' => env('CURRICULUM_DRAFT_VALIDATION_DIRECTORY', storage_path('app/private/curriculum/draft-validation')),
    'published_package_directory' => env('CURRICULUM_PUBLISHED_PACKAGE_DIRECTORY', storage_path('app/private/curriculum/published')),
    'release' => [
        // Draft delivery is a visibly labeled local/testing preview only. The
        // guard still rejects it unconditionally when APP_ENV=production.
        'allow_draft_active_preview' => (bool) env(
            'CURRICULUM_ALLOW_DRAFT_ACTIVE_PREVIEW',
            env('APP_ENV', 'production') !== 'production',
        ),
        // A narrow test-only escape hatch for exercising the one-time legacy
        // completion migration. The runtime guard also requires APP_ENV=testing.
        'allow_unapproved_replacement_for_tests' => false,
    ],
    'import' => [
        'disk' => 'curriculum_private',
        'authority_docx' => env('CURRICULUM_AUTHORITY_DOCX', base_path('tests/Fixtures/curriculum/authority/Hospitrainity.docx')),
        'docx_max_kib' => 2048,
        'asset_max_kib' => 2048,
        // The authority DOCX has 12 entries / 2,269,083 uncompressed bytes;
        // these fail-closed ceilings leave measured headroom without permitting
        // an unbounded OOXML decompression workload.
        'docx_max_entries' => 256,
        'docx_max_uncompressed_bytes' => 16 * 1024 * 1024,
        'docx_max_entry_bytes' => 8 * 1024 * 1024,
        'quarantine_prefix' => 'imports/quarantine',
        'work_prefix' => 'imports/work',
        'blob_prefix' => 'assets/blobs',
        'compiler_timeout_seconds' => 50,
        'compiler_idle_timeout_seconds' => 20,
    ],
];
