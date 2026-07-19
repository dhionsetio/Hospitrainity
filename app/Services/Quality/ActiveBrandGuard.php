<?php

namespace App\Services\Quality;

use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use Illuminate\Support\Facades\File;

final class ActiveBrandGuard
{
    private const PATTERN = '~stay(?:[\s_.-]|<[^>]+>)*ready~iu';

    private const ALLOWED_EXTENSIONS = [
        'css', 'html', 'js', 'json', 'md', 'mjs', 'php', 'txt', 'xml',
    ];

    private const ACTIVE_DIRECTORIES = [
        'app',
        'bootstrap',
        'config',
        'database/factories',
        'database/migrations',
        'database/seeders',
        'lang',
        'public',
        'resources',
        'routes',
        'standalone',
        'curriculum/hospitrainity/0.4.0-draft',
    ];

    private const ACTIVE_FILES = [
        '.env.example',
        'composer.json',
        'package.json',
        'curriculum/evidence/cf-7.json',
    ];

    private const FILE_ALLOWLIST = [
        'curriculum/hospitrainity/0.4.0-draft/provenance/legacy-inventory.json' => [
            'Stay'.'Ready to Hospitrainity rebrand',
        ],
    ];

    /** @return array<string, mixed> */
    public function inspect(): array
    {
        $files = $this->activeFiles();
        $violations = [];
        $allowedFileMatches = [];

        foreach ($files as $relative => $absolute) {
            if (preg_match(self::PATTERN, $relative) === 1) {
                $violations[] = ['surface' => 'filename', 'path' => $relative, 'match' => $relative];
            }

            $content = File::get($absolute);
            preg_match_all(self::PATTERN, $content, $matches);
            foreach ($matches[0] as $match) {
                $allowedPhrase = self::FILE_ALLOWLIST[$relative][0] ?? null;
                if ($match === 'Stay'.'Ready' && is_string($allowedPhrase) && substr_count($content, $allowedPhrase) === 1) {
                    $allowedFileMatches[] = ['path' => $relative, 'match' => $allowedPhrase];
                } else {
                    $violations[] = ['surface' => 'file', 'path' => $relative, 'match' => $match];
                }
            }
        }

        $database = $this->databaseMatches();
        $violations = array_values(array_merge($violations, $database['violations']));

        return [
            'status' => $violations === [] ? 'verified' : 'failed',
            'pattern' => self::PATTERN,
            'files_scanned' => count($files),
            'active_match_count' => count($violations),
            'violations' => $violations,
            'historical_allowlist' => [
                'file_matches' => $allowedFileMatches,
                'database_matches' => $database['allowed'],
            ],
        ];
    }

    /** @return array<string, string> */
    private function activeFiles(): array
    {
        $files = [];
        foreach (self::ACTIVE_DIRECTORIES as $relativeDirectory) {
            $absoluteDirectory = base_path($relativeDirectory);
            if (! File::isDirectory($absoluteDirectory)) {
                continue;
            }
            foreach (File::allFiles($absoluteDirectory) as $file) {
                if (! in_array(strtolower($file->getExtension()), self::ALLOWED_EXTENSIONS, true)) {
                    continue;
                }
                $relative = $this->relativePath($file->getPathname());
                $files[$relative] = $file->getPathname();
            }
        }
        foreach (self::ACTIVE_FILES as $relativeFile) {
            $absoluteFile = base_path($relativeFile);
            if (File::isFile($absoluteFile)) {
                $files[str_replace('\\', '/', $relativeFile)] = $absoluteFile;
            }
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    /** @return array{allowed: list<array<string, string>>, violations: list<array<string, string>>} */
    private function databaseMatches(): array
    {
        $active = CurriculumPackage::active();
        if ($active === null) {
            return ['allowed' => [], 'violations' => []];
        }

        $allowed = [];
        $violations = [];
        $records = [[
            'label' => "curriculum_packages:{$active->id}:projection_meta",
            'entity_type' => 'package',
            'value' => json_encode($active->projection_meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]];
        foreach (CurriculumEntity::query()->where('curriculum_package_id', $active->id)->get(['code', 'entity_type', 'payload']) as $entity) {
            $records[] = [
                'label' => "curriculum_entities:{$entity->code}:payload",
                'entity_type' => $entity->entity_type,
                'value' => json_encode($entity->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ];
        }

        foreach ($records as $record) {
            preg_match_all(self::PATTERN, $record['value'], $matches);
            foreach ($matches[0] as $match) {
                if ($record['label'] === 'curriculum_entities:DOC-PROVENANCE-LEGACY-INVENTORY:payload' && $match === 'Stay'.'Ready' && str_contains($record['value'], 'Stay'.'Ready to Hospitrainity rebrand')) {
                    $allowed[] = ['record' => $record['label'], 'match' => 'Stay'.'Ready to Hospitrainity rebrand'];
                } else {
                    $violations[] = ['surface' => 'database', 'path' => $record['label'], 'match' => $match];
                }
            }
        }

        return ['allowed' => $allowed, 'violations' => $violations];
    }

    private function relativePath(string $absolute): string
    {
        return str_replace('\\', '/', substr($absolute, strlen(base_path()) + 1));
    }
}
