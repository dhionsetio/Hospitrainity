<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Lang;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class TranslationInventoryTest extends TestCase
{
    public function test_literal_user_facing_translation_calls_exist_in_both_locales(): void
    {
        $missing = [];

        foreach ($this->sourceFiles() as $path) {
            $source = (string) file_get_contents($path);
            preg_match_all(
                '~(?<![A-Za-z0-9_])(?:__|trans|trans_choice)\s*\(\s*(["\'])(?<key>(?:\\\\.|(?!\1).)*)\1~s',
                $source,
                $matches,
                PREG_OFFSET_CAPTURE,
            );

            foreach ($matches['key'] as $index => [$key, $offset]) {
                $fullMatch = $matches[0][$index][0];
                $afterMatch = substr($source, $matches[0][$index][1] + strlen($fullMatch), 8);
                if (str_contains($key, '$') || preg_match('/^\s*\./', $afterMatch) === 1) {
                    continue;
                }

                $key = stripcslashes($key);
                foreach (['en', 'id'] as $locale) {
                    if (! Lang::hasForLocale($key, $locale)) {
                        $relativePath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
                        $missing[] = $locale.':'.$key.' @ '.$relativePath.':'.$this->lineNumber($source, $offset);
                    }
                }
            }
        }

        $missing = array_values(array_unique($missing));
        $this->assertSame([], $missing, "Missing translations:\n".implode("\n", $missing));
    }

    public function test_dynamic_translation_families_exist_in_both_locales(): void
    {
        $keys = [
            'admin.confirm_password_description.security',
            'admin.modules',
            'admin.lessons',
            'auth.password',
            ...array_map(fn (string $value): string => 'passwords.'.$value, ['reset', 'sent', 'throttled', 'token', 'user']),
            ...array_map(fn (string $value): string => 'admin.block_types.'.$value, ['paragraph', 'heading', 'callout', 'list_item', 'dialogue_turn', 'source_table', 'external_link', 'instruction', 'activity_embed']),
            ...array_map(fn (string $value): string => 'admin.import_statuses.'.$value, ['rejected', 'quarantined', 'queued', 'processing', 'ready', 'accepted', 'failed']),
            ...array_map(fn (string $value): string => 'admin.asset_kinds.'.$value, ['image', 'audio']),
            ...array_map(fn (string $value): string => 'admin.outcome_types.'.$value, ['knowledge', 'performance', 'reflection']),
            ...array_map(fn (string $value): string => 'admin.entity_types.'.$value, ['chapter', 'lesson-section', 'outcome']),
            'Approved', 'Rejected', 'Cancelled',
            'Access export', 'Correction', 'Restriction', 'Objection', 'Deletion', 'Consent withdrawal', 'Appeal',
            'Submitted', 'Identity pending', 'In review', 'Denied', 'Held', 'Executing', 'Failed', 'Appealed',
        ];

        $missing = [];
        foreach (array_unique($keys) as $key) {
            foreach (['en', 'id'] as $locale) {
                if (! Lang::hasForLocale($key, $locale)) {
                    $missing[] = $locale.':'.$key;
                }
            }
        }

        $this->assertSame([], $missing, "Missing dynamic translations:\n".implode("\n", $missing));
    }

    /** @return list<string> */
    private function sourceFiles(): array
    {
        $files = [];
        foreach ([app_path(), resource_path('views'), base_path('routes')] as $directory) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    private function lineNumber(string $source, int $offset): int
    {
        return substr_count(substr($source, 0, $offset), "\n") + 1;
    }
}
