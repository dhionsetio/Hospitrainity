<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::getDriverName() !== 'sqlite') {
    fwrite(STDERR, "This diagnostic currently supports the project's SQLite database only.\n");
    exit(2);
}

$queries = [
    'published_modules' => [
        'SELECT * FROM modules WHERE is_published = ? ORDER BY `order`, id',
        [1],
    ],
    'ordered_lessons' => [
        'SELECT * FROM lessons WHERE module_id = ? ORDER BY `order`, id',
        [1],
    ],
    'supervisor_learners' => [
        'SELECT * FROM users WHERE role = ? AND instansi = ? ORDER BY id LIMIT 20',
        ['user', 'Hotel B'],
    ],
];

foreach ($queries as $name => [$sql, $bindings]) {
    echo "{$name}:\n";
    foreach (DB::select("EXPLAIN QUERY PLAN {$sql}", $bindings) as $row) {
        echo "  - {$row->detail}\n";
    }
}

$emails = DB::table('users')->orderBy('id')->pluck('email');
$canonicalGroups = $emails->groupBy(static fn (string $email): string => User::canonicalEmail($email));
$nonCanonical = $emails->filter(
    static fn (string $email): bool => $email !== User::canonicalEmail($email),
)->count();
$collisions = $canonicalGroups->filter(static fn ($group): bool => $group->count() > 1)->count();

echo "email_normalization:\n";
echo "  - users: {$emails->count()}\n";
echo "  - non_canonical: {$nonCanonical}\n";
echo "  - canonical_collision_groups: {$collisions}\n";

$integrity = DB::selectOne('PRAGMA integrity_check')->integrity_check ?? 'unknown';
$foreignKeyViolations = count(DB::select('PRAGMA foreign_key_check'));
echo "database_health:\n";
echo "  - integrity_check: {$integrity}\n";
echo "  - foreign_key_violations: {$foreignKeyViolations}\n";
