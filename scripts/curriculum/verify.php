<?php

declare(strict_types=1);

use Hospitrainity\Curriculum\SourceCompiler;

require __DIR__.'/SourceCompiler.php';
require __DIR__.'/AssessmentNormalizer.php';

/** @return array<string, string> */
function parseOptions(array $arguments): array
{
    $options = [];
    for ($index = 1; $index < count($arguments); $index += 2) {
        $name = $arguments[$index] ?? '';
        $value = $arguments[$index + 1] ?? '';
        if (! str_starts_with($name, '--') || $value === '') {
            throw new RuntimeException('Usage: php verify.php --source <Hospitrainity.docx> --baseline <package>');
        }
        $options[substr($name, 2)] = $value;
    }
    foreach (['source', 'baseline'] as $required) {
        if (! isset($options[$required])) {
            throw new RuntimeException("Missing --{$required}");
        }
    }

    return $options;
}

function copyTree(string $source, string $target): void
{
    if (! mkdir($target, 0775, true) && ! is_dir($target)) {
        throw new RuntimeException("Unable to create {$target}");
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $destination = $target.DIRECTORY_SEPARATOR.$relative;
        if ($item->isDir()) {
            if (! is_dir($destination) && ! mkdir($destination, 0775, true) && ! is_dir($destination)) {
                throw new RuntimeException("Unable to create {$destination}");
            }
        } elseif (! copy($item->getPathname(), $destination)) {
            throw new RuntimeException("Unable to copy {$relative}");
        }
    }
}

function removeTree(string $path): void
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

function mutateDocx(string $source, string $target, string $part, callable $mutation): string
{
    if (! copy($source, $target)) {
        throw new RuntimeException("Unable to copy DOCX fixture: {$target}");
    }
    $zip = new ZipArchive;
    if ($zip->open($target) !== true) {
        throw new RuntimeException("Unable to open DOCX fixture: {$target}");
    }
    try {
        $contents = $zip->getFromName($part);
        if (! is_string($contents)) {
            throw new RuntimeException("Fixture part is missing: {$part}");
        }
        $updated = $mutation($contents);
        if (! is_string($updated) || $updated === $contents || ! $zip->addFromString($part, $updated)) {
            throw new RuntimeException("Fixture mutation did not modify {$part}");
        }
    } finally {
        $zip->close();
    }

    return strtolower((string) hash_file('sha256', $target));
}

function expectFailure(string $name, callable $operation, string $messageFragment): array
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
    $options = parseOptions($argv);
    $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hospitrainity-cf1-'.bin2hex(random_bytes(6));
    if (! mkdir($root, 0775, true) && ! is_dir($root)) {
        throw new RuntimeException('Unable to create verification directory.');
    }
    try {
        $buildA = $root.DIRECTORY_SEPARATOR.'build-a';
        $buildB = $root.DIRECTORY_SEPARATOR.'build-b';
        $compilerA = new SourceCompiler($options['source'], $options['baseline'], $buildA);
        $compilerB = new SourceCompiler($options['source'], $options['baseline'], $buildB);
        $compilerA->compile();
        $compilerB->compile();
        $manifestA = $compilerA->treeManifest($buildA);
        $manifestB = $compilerB->treeManifest($buildB);
        if ($manifestA !== $manifestB) {
            throw new RuntimeException('Independent compiler builds are not byte-identical.');
        }
        $determinism = $compilerA->treeSha256($manifestA);

        $probes = [];
        $changed = $root.DIRECTORY_SEPARATOR.'changed-source.docx';
        copy($options['source'], $changed);
        file_put_contents($changed, "\0", FILE_APPEND);
        $probes[] = expectFailure('changed source hash', fn () => (new SourceCompiler($changed, $options['baseline'], $root.'/changed-out'))->compile(), 'hash mismatch');

        $missing = $root.DIRECTORY_SEPARATOR.'missing-marker.docx';
        $missingHash = mutateDocx($options['source'], $missing, 'word/document.xml', static function (string $xml): string {
            return str_replace('>Warm-Up<', '>Warm-Up removed<', $xml);
        });
        $probes[] = expectFailure('missing section marker', fn () => (new SourceCompiler($missing, $options['baseline'], $root.'/missing-out', $missingHash))->compile(), 'marker is missing');

        $duplicateBaseline = $root.DIRECTORY_SEPARATOR.'duplicate-baseline';
        copyTree($options['baseline'], $duplicateBaseline);
        $firstPath = $duplicateBaseline.'/chapters/HSP-C01/sections/HSP-C01-LS-01.json';
        $secondPath = $duplicateBaseline.'/chapters/HSP-C01/sections/HSP-C01-LS-02.json';
        $first = json_decode((string) file_get_contents($firstPath), true, flags: JSON_THROW_ON_ERROR);
        $second = json_decode((string) file_get_contents($secondPath), true, flags: JSON_THROW_ON_ERROR);
        $second['id'] = $first['id'];
        file_put_contents($secondPath, json_encode($second, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $probes[] = expectFailure('duplicated entity IDs', fn () => (new SourceCompiler($options['source'], $duplicateBaseline, $root.'/duplicate-out'))->compile(), 'duplicate baseline entity id');

        $malformed = $root.DIRECTORY_SEPARATOR.'malformed-table.docx';
        $malformedHash = mutateDocx($options['source'], $malformed, 'word/document.xml', static function (string $xml): string {
            $document = new DOMDocument;
            $document->loadXML($xml, LIBXML_NONET);
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $cell = $xpath->query('(//w:tbl)[1]/w:tr[2]/w:tc[last()]')->item(0);
            if (! $cell instanceof DOMElement) {
                throw new RuntimeException('Unable to locate table cell fixture.');
            }
            $cell->parentNode?->removeChild($cell);

            return (string) $document->saveXML();
        });
        $probes[] = expectFailure('malformed table rows', fn () => (new SourceCompiler($malformed, $options['baseline'], $root.'/table-out', $malformedHash))->compile(), 'row width');

        $lost = $root.DIRECTORY_SEPARATOR.'lost-link.docx';
        $lostHash = mutateDocx($options['source'], $lost, 'word/_rels/document.xml.rels', static function (string $xml): string {
            $document = new DOMDocument;
            $document->loadXML($xml, LIBXML_NONET);
            $xpath = new DOMXPath($document);
            $node = $xpath->query('//*[local-name()="Relationship" and @TargetMode="External"]')->item(0);
            if (! $node instanceof DOMElement) {
                throw new RuntimeException('Unable to locate external relationship fixture.');
            }
            $node->parentNode?->removeChild($node);

            return (string) $document->saveXML();
        });
        $probes[] = expectFailure('lost hyperlink relationship', fn () => (new SourceCompiler($lost, $options['baseline'], $root.'/link-out', $lostHash))->compile(), 'relationship');

        $unsupported = $root.DIRECTORY_SEPARATOR.'unsupported-block.docx';
        $unsupportedHash = mutateDocx($options['source'], $unsupported, 'word/document.xml', static function (string $xml): string {
            $position = strrpos($xml, '<w:sectPr');
            if ($position === false) {
                throw new RuntimeException('Unable to locate section properties fixture.');
            }

            return substr($xml, 0, $position).'<w:sdt/>'.substr($xml, $position);
        });
        $probes[] = expectFailure('unsupported block type', fn () => (new SourceCompiler($unsupported, $options['baseline'], $root.'/unsupported-out', $unsupportedHash))->compile(), 'unsupported wordprocessingml body block');

        fwrite(STDOUT, json_encode([
            'status' => 'verified',
            'deterministic_tree_sha256' => $determinism,
            'byte_identical_file_count' => count($manifestA),
            'negative_probes' => $probes,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
    } finally {
        removeTree($root);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, '[curriculum-compiler-verifier] '.$exception->getMessage().PHP_EOL);
    exit(1);
}
