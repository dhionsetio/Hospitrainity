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

class ProgressAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_supported_types_in_published_curriculum_are_accepted(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $lesson = Lesson::factory()->create();
        $vocabulary = Vocabulary::factory()->for($lesson)->create();
        $material = Material::factory()->for($lesson)->create();
        $items = [
            'VocabularyItem' => VocabularyItem::factory()->for($vocabulary)->create(),
            'MaterialItem' => MaterialItem::factory()->for($material)->create(),
            'Exercise' => Exercise::factory()->for($lesson)->create(),
        ];

        foreach ($items as $type => $item) {
            $this->actingAs($user)->postJson(route('progress.store'), [
                'type' => $type,
                'items' => [$item->id],
            ])->assertOk();
        }

        $this->assertDatabaseCount('completions', 3);
    }

    public function test_all_supported_types_in_unpublished_curriculum_are_rejected(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $module = Module::factory()->draft()->create();
        $lesson = Lesson::factory()->for($module)->create();
        $vocabulary = Vocabulary::factory()->for($lesson)->create();
        $material = Material::factory()->for($lesson)->create();
        $items = [
            'VocabularyItem' => VocabularyItem::factory()->for($vocabulary)->create(),
            'MaterialItem' => MaterialItem::factory()->for($material)->create(),
            'Exercise' => Exercise::factory()->for($lesson)->create(),
        ];

        foreach ($items as $type => $item) {
            $this->actingAs($user)->postJson(route('progress.store'), [
                'type' => $type,
                'items' => [$item->id],
            ])->assertUnprocessable()->assertJsonValidationErrors('items');
        }

        $this->assertDatabaseCount('completions', 0);
    }

    public function test_a_mixed_published_and_unpublished_batch_is_rejected_atomically(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $publishedExercise = Exercise::factory()->create();
        $draftModule = Module::factory()->draft()->create();
        $draftLesson = Lesson::factory()->for($draftModule)->create();
        $draftExercise = Exercise::factory()->for($draftLesson)->create();

        $this->actingAs($user)->postJson(route('progress.store'), [
            'type' => 'Exercise',
            'items' => [$publishedExercise->id, $draftExercise->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('completions', 0);
    }

    public function test_progress_payload_is_bounded(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->postJson(route('progress.store'), [
            'type' => 'Exercise',
            'items' => range(1, 101),
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertDatabaseCount('completions', 0);
    }

    public function test_duplicate_ids_in_one_request_are_deduplicated(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $exercise = Exercise::factory()->create();

        $this->actingAs($user)->postJson(route('progress.store'), [
            'type' => 'Exercise',
            'items' => [$exercise->id, $exercise->id],
        ])->assertOk();

        $this->assertDatabaseCount('completions', 1);
    }
}
