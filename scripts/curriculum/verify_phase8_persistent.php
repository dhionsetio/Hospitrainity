<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$databasePath = $root.'/database/database.sqlite';
$backupEvidencePath = $root.'/storage/app/private/backups/phase8-database-before-canonical-import-2026-07-16.json';
$phase14EvidencePath = $root.'/curriculum/evidence/phase-14.json';

$backupEvidence = json_decode((string) file_get_contents($backupEvidencePath), true, flags: JSON_THROW_ON_ERROR);
$phase14Evidence = json_decode((string) file_get_contents($phase14EvidencePath), true, flags: JSON_THROW_ON_ERROR);
$database = new PDO('sqlite:'.$databasePath, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$integrity = $database->query('PRAGMA integrity_check')->fetchColumn();
$foreignKeyViolations = $database->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC);
if ($integrity !== 'ok' || $foreignKeyViolations !== []) {
    throw new RuntimeException('Persistent database integrity or foreign-key verification failed.');
}

$legacyCounts = [];
foreach (array_keys($backupEvidence['backup']['counts']) as $table) {
    $legacyCounts[$table] = (int) $database->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}
if ($legacyCounts !== $backupEvidence['backup']['counts']) {
    throw new RuntimeException('One or more pre-import user/legacy curriculum counts changed unexpectedly.');
}

$entityCounts = [];
foreach ($database->query('SELECT entity_type, COUNT(*) AS aggregate FROM curriculum_entities GROUP BY entity_type ORDER BY entity_type')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $entityCounts[$row['entity_type']] = (int) $row['aggregate'];
}

$package = $database->query('SELECT package_name, content_version, source_tree_sha256, laravel_projection_sha256, standalone_sha256 FROM curriculum_packages WHERE is_active = 1')->fetch(PDO::FETCH_ASSOC);
if (! is_array($package)) {
    throw new RuntimeException('No active canonical package found.');
}

$standalonePath = $root.'/'.$phase14Evidence['standalone']['path'];
$standaloneSha256 = hash_file('sha256', $standalonePath);
if (! hash_equals($phase14Evidence['standalone']['sha256'], $standaloneSha256)) {
    throw new RuntimeException('Persistent standalone checksum does not match phase-14 evidence.');
}

$rollback = $database->query("SELECT rollback_path, rollback_sha256 FROM curriculum_import_runs WHERE status = 'imported' ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (! is_array($rollback) || ! is_file($rollback['rollback_path']) || ! hash_equals($rollback['rollback_sha256'], hash_file('sha256', $rollback['rollback_path']))) {
    throw new RuntimeException('Persistent import rollback artifact is missing or has a checksum mismatch.');
}

$result = [
    'database' => [
        'sha256' => hash_file('sha256', $databasePath),
        'bytes' => filesize($databasePath),
        'integrity_check' => $integrity,
        'foreign_key_violations' => count($foreignKeyViolations),
    ],
    'legacy_counts_unchanged' => true,
    'legacy_counts' => $legacyCounts,
    'canonical' => [
        'package' => $package,
        'packages' => (int) $database->query('SELECT COUNT(*) FROM curriculum_packages')->fetchColumn(),
        'active_packages' => (int) $database->query('SELECT COUNT(*) FROM curriculum_packages WHERE is_active = 1')->fetchColumn(),
        'source_files' => (int) $database->query('SELECT COUNT(*) FROM curriculum_source_files')->fetchColumn(),
        'entities' => (int) $database->query('SELECT COUNT(*) FROM curriculum_entities')->fetchColumn(),
        'links' => (int) $database->query('SELECT COUNT(*) FROM curriculum_links')->fetchColumn(),
        'import_runs' => (int) $database->query('SELECT COUNT(*) FROM curriculum_import_runs')->fetchColumn(),
        'entity_counts' => $entityCounts,
    ],
    'standalone' => [
        'sha256' => $standaloneSha256,
        'bytes' => filesize($standalonePath),
    ],
    'rollback' => [
        'path' => $rollback['rollback_path'],
        'sha256' => $rollback['rollback_sha256'],
        'bytes' => filesize($rollback['rollback_path']),
    ],
];

fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
