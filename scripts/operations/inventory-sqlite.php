<?php

declare(strict_types=1);

if (! extension_loaded('sqlite3')) {
    fwrite(STDERR, "The sqlite3 extension is required.\n");
    exit(1);
}

$options = getopt('', ['database:']);
$databasePath = $options['database'] ?? null;

if (! is_string($databasePath) || $databasePath === '' || ! is_file($databasePath)) {
    fwrite(STDERR, "Usage: php scripts/operations/inventory-sqlite.php --database=<existing SQLite file>\n");
    exit(1);
}

$database = new SQLite3($databasePath, SQLITE3_OPEN_READONLY);
$database->enableExceptions(true);
$database->exec('PRAGMA query_only = ON');

$expectedTables = [
    'users',
    'curriculum_packages',
    'curriculum_activity_progress',
    'curriculum_attempts',
    'curriculum_responses',
    'curriculum_imports',
    'administration_audits',
    'curriculum_draft_events',
];

$tableCounts = [];
foreach ($expectedTables as $table) {
    $existsStatement = $database->prepare(
        "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = :name"
    );
    $existsStatement->bindValue(':name', $table, SQLITE3_TEXT);
    $exists = (int) $existsStatement->execute()->fetchArray(SQLITE3_NUM)[0] === 1;

    if (! $exists) {
        throw new RuntimeException("Required table is absent: {$table}");
    }

    // Identifiers are selected only from the fixed allowlist above.
    $tableCounts[$table] = (int) $database->querySingle("SELECT COUNT(*) FROM {$table}");
}

$packages = [];
$packageResult = $database->query(
    'SELECT id, package_name, content_version, schema_version, is_active, lifecycle_status, source_tree_sha256
     FROM curriculum_packages
     ORDER BY id'
);

while ($row = $packageResult->fetchArray(SQLITE3_ASSOC)) {
    $row['id'] = (int) $row['id'];
    $row['is_active'] = (bool) $row['is_active'];
    $packages[] = $row;
}

$migrationCount = (int) $database->querySingle(
    "SELECT CASE
        WHEN EXISTS (SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'migrations')
        THEN (SELECT COUNT(*) FROM migrations)
        ELSE 0
    END"
);

$inventory = [
    'schema_version' => '1.0.0',
    'database_sha256' => hash_file('sha256', $databasePath),
    'open_mode' => 'SQLITE3_OPEN_READONLY + PRAGMA query_only',
    'migration_rows' => $migrationCount,
    'table_counts' => $tableCounts,
    'packages' => $packages,
];

echo json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
