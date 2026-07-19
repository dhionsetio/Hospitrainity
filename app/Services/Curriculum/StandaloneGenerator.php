<?php

namespace App\Services\Curriculum;

use RuntimeException;

final class StandaloneGenerator
{
    public function __construct(private readonly StandaloneProjectionBuilder $projection) {}

    public function dataJson(CanonicalPackage $package): string
    {
        return CanonicalJson::encode($this->projection->build($package));
    }

    public function render(CanonicalPackage $package): string
    {
        $templatePath = config('curriculum.standalone_template');
        $template = file_get_contents($templatePath);
        if ($template === false) {
            throw new RuntimeException("Unable to read standalone template: {$templatePath}");
        }

        if (substr_count($template, '{{HOSPITRAINITY_DATA}}') !== 1) {
            throw new RuntimeException('Standalone template must contain exactly one curriculum data placeholder.');
        }

        return str_replace('{{HOSPITRAINITY_DATA}}', $this->dataJson($package), $template);
    }

    /** @return array{path: string, sha256: string, bytes: int, changed: bool} */
    public function write(CanonicalPackage $package, ?string $outputPath = null): array
    {
        $outputPath ??= config('curriculum.standalone_output');
        $rendered = $this->render($package);
        $sha256 = hash('sha256', $rendered);
        $expected = $package->evidence['standalone']['sha256'];

        if (! hash_equals(strtolower($expected), $sha256)) {
            throw new RuntimeException("Generated standalone checksum mismatch. Expected {$expected}; found {$sha256}.");
        }

        $existing = is_file($outputPath) ? hash_file('sha256', $outputPath) : null;
        if ($existing !== false && $existing !== null && hash_equals($sha256, $existing)) {
            return ['path' => $outputPath, 'sha256' => $sha256, 'bytes' => strlen($rendered), 'changed' => false];
        }

        $directory = dirname($outputPath);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create standalone output directory: {$directory}");
        }

        $temporary = tempnam($directory, '.hospitrainity-');
        if ($temporary === false) {
            throw new RuntimeException("Unable to allocate a temporary standalone file in {$directory}.");
        }

        try {
            $written = file_put_contents($temporary, $rendered, LOCK_EX);
            if ($written !== strlen($rendered) || ! hash_equals($sha256, (string) hash_file('sha256', $temporary))) {
                throw new RuntimeException('Temporary standalone output failed its write/checksum verification.');
            }
            if (! rename($temporary, $outputPath)) {
                throw new RuntimeException("Unable to atomically promote standalone output to {$outputPath}.");
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }

        return ['path' => $outputPath, 'sha256' => $sha256, 'bytes' => strlen($rendered), 'changed' => true];
    }
}
