<?php

declare(strict_types=1);

use Hospitrainity\Curriculum\SourceCompiler;

require __DIR__.'/SourceCompiler.php';
require __DIR__.'/AssessmentNormalizer.php';

/** @return array<string, string|bool> */
function options(array $arguments): array
{
    $result = [];
    for ($index = 1; $index < count($arguments); $index++) {
        $argument = $arguments[$index];
        if (! str_starts_with($argument, '--')) {
            throw new RuntimeException("Unexpected argument: {$argument}");
        }
        $name = substr($argument, 2);
        if ($name === 'force') {
            $result[$name] = true;

            continue;
        }
        $value = $arguments[++$index] ?? null;
        if ($value === null || str_starts_with($value, '--')) {
            throw new RuntimeException("Missing value for --{$name}");
        }
        $result[$name] = $value;
    }

    return $result;
}

try {
    $options = options($argv);
    foreach (['source', 'baseline', 'output'] as $required) {
        if (! isset($options[$required]) || ! is_string($options[$required])) {
            throw new RuntimeException("Required option is missing: --{$required}");
        }
    }
    $compiler = new SourceCompiler(
        sourcePath: $options['source'],
        baselinePath: $options['baseline'],
        outputPath: $options['output'],
        expectedSha256: is_string($options['expected-sha256'] ?? null) ? $options['expected-sha256'] : SourceCompiler::AUTHORITY_SHA256,
        sourceArtifact: is_string($options['source-artifact'] ?? null) ? $options['source-artifact'] : null,
    );
    $summary = $compiler->compile((bool) ($options['force'] ?? false));
    fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, '[curriculum-compiler] '.$exception->getMessage().PHP_EOL);
    exit(1);
}
