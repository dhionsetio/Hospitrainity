<?php

declare(strict_types=1);

use Hospitrainity\Curriculum\NextGenerationSourceCompiler;

require __DIR__.'/NextGenerationSourceCompiler.php';

/** @return array<string,string|bool> */
function nextCompilerOptions(array $arguments): array
{
    $options = [];
    for ($index = 1; $index < count($arguments); $index++) {
        $argument = $arguments[$index];
        if (! str_starts_with($argument, '--')) {
            throw new RuntimeException("Unexpected argument: {$argument}");
        }
        $name = substr($argument, 2);
        if ($name === 'force') {
            $options[$name] = true;

            continue;
        }
        $value = $arguments[++$index] ?? null;
        if (! is_string($value) || str_starts_with($value, '--')) {
            throw new RuntimeException("Missing value for --{$name}");
        }
        $options[$name] = $value;
    }

    return $options;
}

try {
    $options = nextCompilerOptions($argv);
    foreach (['source', 'output'] as $required) {
        if (! isset($options[$required]) || ! is_string($options[$required])) {
            throw new RuntimeException("Required option is missing: --{$required}");
        }
    }
    $compiler = new NextGenerationSourceCompiler(
        sourcePath: $options['source'],
        outputPath: $options['output'],
        expectedSha256: is_string($options['expected-sha256'] ?? null) ? $options['expected-sha256'] : NextGenerationSourceCompiler::AUTHORITY_SHA256,
        sourceArtifact: is_string($options['source-artifact'] ?? null) ? $options['source-artifact'] : 'Hospitrainity.docx',
    );
    $summary = $compiler->compile((bool) ($options['force'] ?? false));
    fwrite(STDOUT, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, '[next-curriculum-compiler] '.$exception->getMessage().PHP_EOL);
    exit(1);
}
