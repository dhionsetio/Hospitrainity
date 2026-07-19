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
use Tests\TestCase;

/**
 * Covers the Phase 3 progress-tracking fix: a lesson's completion percentage
 * counts vocabulary items, material items AND exercises (previously only
 * vocabulary was counted). Also covers module + overall aggregation.
 */
class LessonProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_lesson_reports_zero_progress(): void
    {
        $lesson = Lesson::factory()->create();
        $user = User::factory()->create();

        $this->assertSame(0, $lesson->getProgressFor($user));
    }

    public function test_progress_counts_vocab_material_and_exercises(): void
    {
        $lesson = Lesson::factory()->create();

        // 2 vocabulary items + 1 material item + 1 exercise = 4 completable units.
        $vocab = Vocabulary::factory()->create(['lesson_id' => $lesson->id]);
        $vocabItems = VocabularyItem::factory()->count(2)->create(['vocabulary_id' => $vocab->id]);

        $material = Material::factory()->create(['lesson_id' => $lesson->id]);
        MaterialItem::factory()->create(['material_id' => $material->id]);

        $exercise = Exercise::factory()->create(['lesson_id' => $lesson->id]);

        $user = User::factory()->create();

        // Complete 2 of the 4 units (one vocab item + the exercise) => 50%.
        $user->completions()->create([
            'completable_id' => $vocabItems->first()->id,
            'completable_type' => VocabularyItem::class,
        ]);
        $user->completions()->create([
            'completable_id' => $exercise->id,
            'completable_type' => Exercise::class,
        ]);

        $this->assertSame(50, $lesson->getProgressFor($user));
    }

    public function test_fully_completed_lesson_reports_one_hundred(): void
    {
        $lesson = Lesson::factory()->create();
        $exercise = Exercise::factory()->create(['lesson_id' => $lesson->id]);
        $user = User::factory()->create();

        $user->completions()->create([
            'completable_id' => $exercise->id,
            'completable_type' => Exercise::class,
        ]);

        $this->assertSame(100, $lesson->getProgressFor($user));
    }

    public function test_module_progress_averages_its_lessons(): void
    {
        $module = Module::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);
        $exercise = Exercise::factory()->create(['lesson_id' => $lesson->id]);
        $user = User::factory()->create();

        $this->assertSame(0, $module->getProgressForUser($user));

        $user->completions()->create([
            'completable_id' => $exercise->id,
            'completable_type' => Exercise::class,
        ]);

        $this->assertSame(100, $module->getProgressForUser($user));
    }

    public function test_overall_progress_only_counts_published_modules(): void
    {
        $published = Module::factory()->create(['is_published' => true]);
        $draft = Module::factory()->create(['is_published' => false]);

        $publishedLesson = Lesson::factory()->create(['module_id' => $published->id]);
        $publishedExercise = Exercise::factory()->create(['lesson_id' => $publishedLesson->id]);

        $draftLesson = Lesson::factory()->create(['module_id' => $draft->id]);
        Exercise::factory()->create(['lesson_id' => $draftLesson->id]);

        $user = User::factory()->create();
        $user->completions()->create([
            'completable_id' => $publishedExercise->id,
            'completable_type' => Exercise::class,
        ]);

        // Draft module is ignored, so the single published module drives the score.
        $this->assertSame(100, $user->getOverallProgress());
    }
}
