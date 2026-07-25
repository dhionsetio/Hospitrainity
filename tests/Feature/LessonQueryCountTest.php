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

class LessonQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_lesson_detail_query_count_is_bounded_as_nested_content_grows(): void
    {
        $user = User::factory()->create();
        $module = Module::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);

        $this->seedNestedContent($lesson, 1);
        $small = $this->countQueries($user, $lesson);

        $this->seedNestedContent($lesson, 20);
        $large = $this->countQueries($user, $lesson);

        $this->assertSame($small, $large, "Lesson query count grew from {$small} to {$large}.");
        // Includes the fixed B03 account-wide privileged-role assurance query.
        $this->assertLessThanOrEqual(20, $large);

        $response = $this->actingAs($user)->get(route('lessons.show', $lesson));
        $loadedLesson = $response->viewData('lesson');
        $this->assertTrue($loadedLesson->relationLoaded('module'));
        $this->assertTrue($loadedLesson->relationLoaded('vocabularies'));
        $this->assertTrue($loadedLesson->relationLoaded('materials'));
        $this->assertTrue($loadedLesson->relationLoaded('exercises'));
        $this->assertTrue($loadedLesson->vocabularies->every->relationLoaded('items'));
        $this->assertTrue($loadedLesson->materials->every->relationLoaded('items'));
    }

    private function seedNestedContent(Lesson $lesson, int $count): void
    {
        for ($index = 0; $index < $count; $index++) {
            $vocabulary = Vocabulary::factory()->create(['lesson_id' => $lesson->id]);
            VocabularyItem::factory()->create(['vocabulary_id' => $vocabulary->id]);

            $material = Material::factory()->create(['lesson_id' => $lesson->id]);
            MaterialItem::factory()->create(['material_id' => $material->id]);

            Exercise::factory()->create(['lesson_id' => $lesson->id]);
        }
    }

    private function countQueries(User $user, Lesson $lesson): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('lessons.show', $lesson))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
