<?php

declare(strict_types=1);

use Hospitrainity\Curriculum\NextGenerationSourceCompiler;

require __DIR__.'/NextGenerationSourceCompiler.php';

/** @return array<string,string> */
function nextVerifierOptions(array $arguments): array
{
    $options = [];
    for ($index = 1; $index < count($arguments); $index += 2) {
        $name = $arguments[$index] ?? '';
        $value = $arguments[$index + 1] ?? '';
        if (! str_starts_with($name, '--') || $value === '') {
            throw new RuntimeException('Usage: php verify_next.php --source <Hospitrainity New.docx>');
        }
        $options[substr($name, 2)] = $value;
    }
    if (! isset($options['source'])) {
        throw new RuntimeException('Missing --source');
    }

    return $options;
}

function nextRemoveTree(string $path): void
{
    if (! is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

function nextMutateDocx(string $source, string $target, callable $mutation): string
{
    if (! copy($source, $target)) {
        throw new RuntimeException('Unable to copy negative-probe DOCX.');
    }
    $zip = new ZipArchive;
    if ($zip->open($target) !== true) {
        throw new RuntimeException('Unable to open negative-probe DOCX.');
    }
    try {
        $xml = $zip->getFromName('word/document.xml');
        if (! is_string($xml)) {
            throw new RuntimeException('Negative-probe DOCX has no document XML.');
        }
        $updated = $mutation($xml);
        if (! is_string($updated) || $updated === $xml || ! $zip->addFromString('word/document.xml', $updated)) {
            throw new RuntimeException('Negative-probe mutation did not change the document.');
        }
    } finally {
        $zip->close();
    }

    return strtolower((string) hash_file('sha256', $target));
}

/** @return array{name:string,status:string,evidence:string} */
function nextExpectFailure(string $name, callable $operation, string $messageFragment): array
{
    try {
        $operation();
    } catch (Throwable $exception) {
        if (! str_contains(strtolower($exception->getMessage()), strtolower($messageFragment))) {
            throw new RuntimeException("{$name} failed for the wrong reason: {$exception->getMessage()}", previous: $exception);
        }

        return ['name' => $name, 'status' => 'passed', 'evidence' => $exception->getMessage()];
    }
    throw new RuntimeException("{$name} did not fail closed.");
}

try {
    $options = nextVerifierOptions($argv);
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hospitrainity-v08-'.bin2hex(random_bytes(6));
    if (! mkdir($root, 0775, true) && ! is_dir($root)) {
        throw new RuntimeException('Unable to create v0.8 verification directory.');
    }
    try {
        $buildA = $root.DIRECTORY_SEPARATOR.'build-a';
        $buildB = $root.DIRECTORY_SEPARATOR.'build-b';
        $compilerA = new NextGenerationSourceCompiler($options['source'], $buildA);
        $compilerB = new NextGenerationSourceCompiler($options['source'], $buildB);
        $summaryA = $compilerA->compile();
        $summaryB = $compilerB->compile();
        $manifestA = $compilerA->treeManifest($buildA);
        $manifestB = $compilerB->treeManifest($buildB);
        if ($manifestA !== $manifestB || $summaryA !== $summaryB) {
            throw new RuntimeException('Independent v0.8 compiler builds are not byte-identical.');
        }

        $probes = [];
        $changed = $root.DIRECTORY_SEPARATOR.'changed-source.docx';
        if (! copy($options['source'], $changed) || file_put_contents($changed, "\0", FILE_APPEND) === false) {
            throw new RuntimeException('Unable to create changed-hash probe.');
        }
        $probes[] = nextExpectFailure(
            'changed source hash',
            fn () => (new NextGenerationSourceCompiler($changed, $root.'/changed-out'))->compile(),
            'hash mismatch',
        );

        $missingChapter = $root.DIRECTORY_SEPARATOR.'missing-chapter.docx';
        $missingChapterHash = nextMutateDocx(
            $options['source'],
            $missingChapter,
            static fn (string $xml): string => str_replace('Chapter HSP-C07:', 'Chapter HSP-X07:', $xml),
        );
        $probes[] = nextExpectFailure(
            'missing coded chapter',
            fn () => (new NextGenerationSourceCompiler($missingChapter, $root.'/missing-out', $missingChapterHash))->compile(),
            'seven coded chapter headings',
        );

        $duplicateCode = $root.DIRECTORY_SEPARATOR.'duplicate-code.docx';
        $duplicateCodeHash = nextMutateDocx(
            $options['source'],
            $duplicateCode,
            static fn (string $xml): string => preg_replace('/HSP-C02-MP-002/u', 'HSP-C02-MP-001', $xml, 1) ?? $xml,
        );
        $probes[] = nextExpectFailure(
            'duplicate stable code',
            fn () => (new NextGenerationSourceCompiler($duplicateCode, $root.'/duplicate-out', $duplicateCodeHash))->compile(),
            'defined more than once',
        );

        $emDash = $root.DIRECTORY_SEPARATOR.'learner-em-dash.docx';
        $emDashHash = nextMutateDocx(
            $options['source'],
            $emDash,
            static fn (string $xml): string => str_replace(
                'In one word, how are you feeling today?',
                'In one word, how are you feeling '."\u{2014}".' today?',
                $xml,
            ),
        );
        $probes[] = nextExpectFailure(
            'learner-facing em dash',
            fn () => (new NextGenerationSourceCompiler($emDash, $root.'/em-dash-out', $emDashHash))->compile(),
            'em dash',
        );

        fwrite(STDOUT, json_encode([
            'status' => 'verified',
            'byte_identical_file_count' => count($manifestA),
            'deterministic_tree_sha256' => $compilerA->treeSha256($manifestA),
            'negative_probes' => $probes,
            'summary' => $summaryA,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL);
    } finally {
        nextRemoveTree($root);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, '[next-curriculum-verifier] '.$exception->getMessage().PHP_EOL);
    exit(1);
}
