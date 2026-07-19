<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\Module;
use App\Models\Vocabulary;
use App\Models\VocabularyItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * RETIRED legacy fixture loader for Modules + Lessons + Vocabulary.
 *
 * database/data/course.json is preserved as migration evidence only. Current
 * learner delivery comes from the versioned checksum-locked curriculum package
 * through `hospitrainity:curriculum import`; DatabaseSeeder no longer calls this
 * class. Do not describe this file or its data as canonical.
 *
 * Idempotent + database-agnostic by design:
 *   - Uses updateOrCreate keyed on natural keys (module slug, lesson slug,
 *     lesson_id + category, vocabulary_id + term) so re-running never creates
 *     duplicates and never orphans the polymorphic completions that reference
 *     vocabulary_items.
 *   - Does NOT call truncate() or raw "SET FOREIGN_KEY_CHECKS" statements. Those
 *     are MySQL-only and THROW on SQLite (which the automated test suite / CI
 *     use). This seeder now runs unchanged on MySQL, SQLite and PostgreSQL.
 */
class VocabularySeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/course.json');

        if (! File::exists($path)) {
            throw new \RuntimeException("Canonical course data not found at {$path}");
        }

        $course = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        $modules = $course['modules'] ?? [];

        foreach ($modules as $moduleIndex => $moduleData) {
            $module = Module::updateOrCreate(
                ['slug' => Str::slug($moduleData['title'])],
                [
                    'title' => $moduleData['title'],
                    'description' => $moduleData['description'] ?? null,
                    'level' => $moduleData['level'] ?? 'beginner',
                    'order' => $moduleIndex + 1,
                    'is_published' => $moduleData['is_published'] ?? true,
                ]
            );

            foreach (($moduleData['lessons'] ?? []) as $lessonIndex => $lessonData) {
                $lesson = Lesson::updateOrCreate(
                    ['slug' => Str::slug($lessonData['title'])],
                    [
                        'module_id' => $module->id,
                        'title' => $lessonData['title'],
                        'order' => $lessonIndex + 1,
                    ]
                );

                $this->seedLessonVocab($lesson, $lessonData['vocabularies'] ?? []);
            }
        }
    }

    /**
     * Upsert one lesson's vocabulary, grouped into categories in the order the
     * categories first appear in the canonical data.
     */
    private function seedLessonVocab(Lesson $lesson, array $vocabData): void
    {
        $grouped = collect($vocabData)->groupBy('category');

        $categoryOrder = 0;
        foreach ($grouped as $categoryName => $items) {
            $category = Vocabulary::updateOrCreate(
                ['lesson_id' => $lesson->id, 'category' => $categoryName],
                ['order' => ++$categoryOrder]
            );

            $itemOrder = 0;
            foreach ($items as $item) {
                VocabularyItem::updateOrCreate(
                    ['vocabulary_id' => $category->id, 'term' => $item['term']],
                    [
                        'details' => $item['details'] ?? null,
                        'order' => ++$itemOrder,
                    ]
                );
            }
        }
    }
}
