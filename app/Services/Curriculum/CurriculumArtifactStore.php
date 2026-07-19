<?php

namespace App\Services\Curriculum;

use JsonException;
use RuntimeException;

final class CurriculumArtifactStore
{
    /** @param array<string, mixed> $data */
    public function writeJson(string $path, array $data): array
    {
        $contents = CanonicalJson::encode($data, pretty: true)."\n";
        $this->writeRaw($path, $contents);

        return [
            'path' => $path,
            'sha256' => hash('sha256', $contents),
            'bytes' => strlen($contents),
        ];
    }

    public function writeRaw(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create artifact directory: {$directory}");
        }

        $temporary = tempnam($directory, '.hsp-artifact-');
        if ($temporary === false) {
            throw new RuntimeException("Unable to allocate a temporary artifact in {$directory}.");
        }

        try {
            $written = file_put_contents($temporary, $contents, LOCK_EX);
            if ($written !== strlen($contents)) {
                throw new RuntimeException("Incomplete artifact write: {$path}");
            }
            if (! rename($temporary, $path)) {
                throw new RuntimeException("Unable to atomically promote artifact: {$path}");
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function readVerifiedJson(string $path, string $expectedSha256): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Unable to read artifact: {$path}");
        }

        $actual = hash('sha256', $contents);
        if (! hash_equals(strtolower($expectedSha256), $actual)) {
            throw new RuntimeException("Artifact checksum mismatch for {$path}. Expected {$expectedSha256}; found {$actual}.");
        }

        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException("Artifact must contain a JSON object: {$path}");
        }

        return $decoded;
    }
}
