<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$source = $root.'/database/database.sqlite';
$directory = $root.'/storage/app/private/backups';
$backup = $directory.'/phase8-database-before-canonical-import-2026-07-16.sqlite';
$evidencePath = $directory.'/phase8-database-before-canonical-import-2026-07-16.json';

if (! is_file($source)) {
    throw new RuntimeException("SQLite source database not found: {$source}");
}
if (file_exists($backup) || file_exists($evidencePath)) {
    throw new RuntimeException('Phase 8 backup/evidence already exists; refusing to overwrite immutable evidence.');
}
if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
    throw new RuntimeException("Unable to create backup directory: {$directory}");
}

$sourceHash = hash_file('sha256', $source);
$sourceBytes = filesize($source);
$sourceDatabase = new PDO('sqlite:'.$source, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sourceDatabase->exec('VACUUM INTO '.$sourceDatabase->quote($backup));

$backupDatabase = new PDO('sqlite:'.$backup, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$integrity = $backupDatabase->query('PRAGMA integrity_check')->fetchColumn();
$foreignKeyViolations = $backupDatabase->query('PRAGMA foreign_key_check')->fetchAll(PDO::FETCH_ASSOC);

$counts = [];
foreach (['users', 'modules', 'lessons', 'vocabularies', 'vocabulary_items', 'materials', 'material_items', 'exercises', 'completions'] as $table) {
    $counts[$table] = (int) $backupDatabase->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}

$evidence = [
    'evidence_version' => '1.0.0',
    'created_at' => date(DATE_ATOM),
    'method' => 'SQLite VACUUM INTO consistent snapshot',
    'source' => [
        'path' => 'database/database.sqlite',
        'sha256' => $sourceHash,
        'bytes' => $sourceBytes,
    ],
    'backup' => [
        'path' => 'storage/app/private/backups/'.basename($backup),
        'sha256' => hash_file('sha256', $backup),
        'bytes' => filesize($backup),
        'integrity_check' => $integrity,
        'foreign_key_violations' => count($foreignKeyViolations),
        'counts' => $counts,
    ],
];

$encoded = json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
if (file_put_contents($evidencePath, $encoded, LOCK_EX) !== strlen($encoded)) {
    throw new RuntimeException("Unable to write backup evidence: {$evidencePath}");
}

fwrite(STDOUT, $encoded);
