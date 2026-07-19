<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\Material;
use Illuminate\Database\Seeder;

/**
 * RETIRED legacy fixture helper that seeds one generic text material per lesson.
 *
 * Rationale: prior to this, NO seeder created materials at all, so the
 * materials feature (lessons.material.show) had zero data. This makes the
 * feature demonstrable end-to-end.
 *
 * Idempotent by design: uses updateOrCreate keyed on (lesson_id, type) for the
 * Material and on (material_id, title) for its item. It does NOT truncate, so
 * re-running never duplicates rows and never orphans the polymorphic
 * completions that may reference material_items.
 *
 * DatabaseSeeder no longer calls this class. The copy below is intentionally
 * generic scaffolding derived from each
 * lesson's own title — it is NOT authoritative lesson content. Replace it via
 * the superadmin Materials manager when real content is available.
 */
class MaterialSeeder extends Seeder
{
    public function run(): void
    {
        Lesson::orderBy('id')->get()->each(function (Lesson $lesson) {
            $material = Material::updateOrCreate(
                ['lesson_id' => $lesson->id, 'type' => 'Teks'],
                ['order' => 1]
            );

            $material->items()->updateOrCreate(
                ['title' => 'Lesson Overview'],
                [
                    'description' => 'Review the key vocabulary and phrases introduced in "'
                        .$lesson->title
                        .'". Practice each term aloud, then complete the practice and exercises for this lesson.',
                    'order' => 1,
                ]
            );
        });
    }
}
