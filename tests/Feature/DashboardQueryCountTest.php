<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\MaterialItem;
use App\Models\Module;
use App\Models\User;
use App\Models\Vocabulary;
use App\Models\VocabularyItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 4 (Dashboard & progress performance): the dashboard must issue a
 * bounded number of queries that does NOT grow with the number of lessons.
 *
 * Before this phase, DashboardController called Lesson::getProgressFor() per
 * lesson, which re-queried vocabularies/materials/exercises and ran 3 count()
 * queries each — roughly 8 queries per lesson. Now a single completions query
 * covers the whole page.
 */
class DashboardQueryCountTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create one published module containing $lessonCount fully-populated
     * lessons (2 vocab items + 1 material item + 1 exercise each).
     */
    private function seedModuleWithLessons(int $lessonCount): void
    {
        $module = Module::factory()->create(['is_published' => true]);

        for ($i = 0; $i < $lessonCount; $i++) {
            $lesson = Lesson::factory()->create(['module_id' => $module->id]);

            $vocab = Vocabulary::factory()->create(['lesson_id' => $lesson->id]);
            VocabularyItem::factory()->count(2)->create(['vocabulary_id' => $vocab->id]);

            $material = Material::factory()->create(['lesson_id' => $lesson->id]);
            MaterialItem::factory()->create(['material_id' => $material->id]);

            Exercise::factory()->create(['lesson_id' => $lesson->id]);
        }
    }

    /**
     * Load /dashboard as a fresh verified learner and return the number of
     * database queries the request issued.
     */
    private function countDashboardQueries(): int
    {
        $user = User::factory()->create(); // verified by default

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($user)->get(route('dashboard'));
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();

        return $count;
    }

    public function test_dashboard_query_count_does_not_grow_with_lessons(): void
    {
        // Small fixture.
        $this->seedModuleWithLessons(3);
        $small = $this->countDashboardQueries();

        // Add many more lessons (a second module with 20 lessons). A flat, N+1-free
        // implementation must issue the SAME number of queries for the larger page.
        $this->seedModuleWithLessons(20);
        $large = $this->countDashboardQueries();

        $this->assertSame(
            $small,
            $large,
            "Dashboard query count grew from {$small} to {$large} as lessons increased — N+1 regression."
        );

        // And the absolute count stays small and bounded.
        $this->assertLessThanOrEqual(
            15,
            $large,
            "Dashboard issued {$large} queries; expected a small, bounded number."
        );
    }

    public function test_dashboard_progress_values_are_correct(): void
    {
        // One module, one lesson: 2 vocab items + 1 material item + 1 exercise = 4 units.
        $module = Module::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);

        $vocab = Vocabulary::factory()->create(['lesson_id' => $lesson->id]);
        $vocabItems = VocabularyItem::factory()->count(2)->create(['vocabulary_id' => $vocab->id]);

        $material = Material::factory()->create(['lesson_id' => $lesson->id]);
        MaterialItem::factory()->create(['material_id' => $material->id]);

        $exercise = Exercise::factory()->create(['lesson_id' => $lesson->id]);

        $user = User::factory()->create();

        // Complete 2 of 4 units => the lesson (and its single-lesson module) = 50%.
        $user->completions()->create([
            'completable_id' => $vocabItems->first()->id,
            'completable_type' => VocabularyItem::class,
        ]);
        $user->completions()->create([
            'completable_id' => $exercise->id,
            'completable_type' => Exercise::class,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();

        $modules = $response->viewData('modules');
        $this->assertEqualsWithDelta(50, $modules->firstWhere('id', $module->id)->progress, 0.0001);
        $this->assertSame(1, $modules->firstWhere('id', $module->id)->lessons_count);
    }
}
