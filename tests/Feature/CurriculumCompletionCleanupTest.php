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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CurriculumCompletionCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpublishing_a_module_purges_descendant_completions_and_cache(): void
    {
        [$user, $module, , $vocabularyItem, $materialItem, $exercise] = $this->curriculumFixture();
        $this->complete($user, $vocabularyItem, $materialItem, $exercise);
        Cache::put(User::progressCacheKey($user->id), 100);

        $module->update(['is_published' => false]);

        $this->assertDatabaseCount('completions', 0);
        $this->assertFalse(Cache::has(User::progressCacheKey($user->id)));
    }

    public function test_deleting_a_lesson_purges_every_descendant_completion(): void
    {
        [$user, , $lesson, $vocabularyItem, $materialItem, $exercise] = $this->curriculumFixture();
        $this->complete($user, $vocabularyItem, $materialItem, $exercise);

        $lesson->delete();

        $this->assertDatabaseCount('completions', 0);
    }

    public function test_deleting_a_module_purges_every_descendant_completion(): void
    {
        [$user, $module, , $vocabularyItem, $materialItem, $exercise] = $this->curriculumFixture();
        $this->complete($user, $vocabularyItem, $materialItem, $exercise);

        $module->delete();

        $this->assertDatabaseCount('completions', 0);
    }

    public function test_deleting_vocabulary_or_material_purges_its_item_completions(): void
    {
        [$user, , , $vocabularyItem, $materialItem] = $this->curriculumFixture();
        $this->complete($user, $vocabularyItem, $materialItem);

        $vocabularyItem->vocabulary->delete();
        $this->assertDatabaseMissing('completions', [
            'completable_type' => VocabularyItem::class,
            'completable_id' => $vocabularyItem->id,
        ]);

        $materialItem->material->delete();
        $this->assertDatabaseCount('completions', 0);
    }

    public function test_content_change_resets_completion_but_reordering_does_not(): void
    {
        [$user, , , , , $exercise] = $this->curriculumFixture();
        $this->complete($user, $exercise);

        $exercise->update(['order' => 20]);
        $this->assertDatabaseCount('completions', 1);

        $exercise->update(['title' => 'Changed learning task']);
        $this->assertDatabaseCount('completions', 0);
    }

    public function test_adding_a_completable_unit_invalidates_all_learner_progress_caches(): void
    {
        $firstUser = User::factory()->create(['role' => 'user']);
        $secondUser = User::factory()->create(['role' => 'user']);
        $lesson = Lesson::factory()->create();
        Cache::put(User::progressCacheKey($firstUser->id), 10);
        Cache::put(User::progressCacheKey($secondUser->id), 20);

        Exercise::factory()->for($lesson)->create();

        $this->assertFalse(Cache::has(User::progressCacheKey($firstUser->id)));
        $this->assertFalse(Cache::has(User::progressCacheKey($secondUser->id)));
    }

    public function test_curriculum_cache_invalidation_does_not_enumerate_learners(): void
    {
        User::factory()->count(100)->create(['role' => 'user']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        User::forgetAllProgressCaches();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertFalse(collect($queries)->contains(
            static fn (array $query): bool => str_contains(strtolower($query['query']), 'from "users"'),
        ));
    }

    /**
     * @return array{User, Module, Lesson, VocabularyItem, MaterialItem, Exercise}
     */
    private function curriculumFixture(): array
    {
        $user = User::factory()->create(['role' => 'user']);
        $module = Module::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->for($module)->create();
        $vocabulary = Vocabulary::factory()->for($lesson)->create();
        $material = Material::factory()->for($lesson)->create();

        return [
            $user,
            $module,
            $lesson,
            VocabularyItem::factory()->for($vocabulary)->create(),
            MaterialItem::factory()->for($material)->create(),
            Exercise::factory()->for($lesson)->create(),
        ];
    }

    private function complete(User $user, object ...$items): void
    {
        foreach ($items as $item) {
            $user->completions()->create([
                'completable_type' => $item::class,
                'completable_id' => $item->getKey(),
            ]);
        }
    }
}
