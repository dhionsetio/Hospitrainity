<?php

declare(strict_types=1);

use App\Services\Curriculum\CanonicalPackageReader;
use App\Services\Curriculum\StandaloneGenerator;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$output = $argv[1] ?? config('curriculum.standalone_output');
$package = $app->make(CanonicalPackageReader::class)->read();
$rendered = $app->make(StandaloneGenerator::class)->render($package);
$directory = dirname($output);
if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
    throw new RuntimeException("Unable to create standalone directory: {$directory}");
}
$temporary = tempnam($directory, '.hospitrainity-render-');
if ($temporary === false) {
    throw new RuntimeException('Unable to allocate a standalone temporary file.');
}
try {
    if (file_put_contents($temporary, $rendered, LOCK_EX) !== strlen($rendered)) {
        throw new RuntimeException('Standalone render was not written completely.');
    }
    $hash = hash('sha256', $rendered);
    if (! hash_equals($hash, (string) hash_file('sha256', $temporary)) || ! rename($temporary, $output)) {
        throw new RuntimeException('Standalone render failed checksum or atomic promotion.');
    }
    fwrite(STDOUT, json_encode(['path' => $output, 'bytes' => strlen($rendered), 'sha256' => $hash], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
} finally {
    if (is_file($temporary)) {
        unlink($temporary);
    }
}
