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

class CurriculumOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_curriculum_relationships_use_order_then_id_as_a_stable_tie_breaker(): void
    {
        $module = Module::factory()->create(['order' => 1]);
        $laterModule = Module::factory()->create(['order' => 2]);

        $firstLesson = Lesson::factory()->create(['module_id' => $module->id, 'order' => 4]);
        $secondLesson = Lesson::factory()->create(['module_id' => $module->id, 'order' => 4]);
        $lastLesson = Lesson::factory()->create(['module_id' => $module->id, 'order' => 8]);

        $firstVocabulary = Vocabulary::factory()->create(['lesson_id' => $firstLesson->id, 'order' => 2]);
        $secondVocabulary = Vocabulary::factory()->create(['lesson_id' => $firstLesson->id, 'order' => 2]);
        $lastVocabulary = Vocabulary::factory()->create(['lesson_id' => $firstLesson->id, 'order' => 9]);

        $firstItem = VocabularyItem::factory()->create(['vocabulary_id' => $firstVocabulary->id, 'order' => 1]);
        $secondItem = VocabularyItem::factory()->create(['vocabulary_id' => $firstVocabulary->id, 'order' => 1]);
        $lastItem = VocabularyItem::factory()->create(['vocabulary_id' => $firstVocabulary->id, 'order' => 7]);

        $firstMaterial = Material::factory()->create(['lesson_id' => $firstLesson->id, 'order' => 3]);
        $secondMaterial = Material::factory()->create(['lesson_id' => $firstLesson->id, 'order' => 3]);
        $materialItem = MaterialItem::factory()->create(['material_id' => $firstMaterial->id, 'order' => 5]);

        $firstExercise = Exercise::factory()->create(['lesson_id' => $firstLesson->id, 'order' => 6]);
        $secondExercise = Exercise::factory()->create(['lesson_id' => $firstLesson->id, 'order' => 6]);

        $this->assertSame([$module->id, $laterModule->id], Module::curriculumOrder()->pluck('id')->all());
        $this->assertSame([$firstLesson->id, $secondLesson->id, $lastLesson->id], $module->fresh()->lessons->pluck('id')->all());
        $this->assertSame([$firstVocabulary->id, $secondVocabulary->id, $lastVocabulary->id], $firstLesson->fresh()->vocabularies->pluck('id')->all());
        $this->assertSame([$firstItem->id, $secondItem->id, $lastItem->id], $firstVocabulary->fresh()->items->pluck('id')->all());
        $this->assertSame([$firstMaterial->id, $secondMaterial->id], $firstLesson->fresh()->materials->pluck('id')->all());
        $this->assertSame([$materialItem->id], $firstMaterial->fresh()->items->pluck('id')->all());
        $this->assertSame([$firstExercise->id, $secondExercise->id], $firstLesson->fresh()->exercises->pluck('id')->all());
    }

    public function test_admin_order_inputs_are_exposed_validated_and_persisted(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        $module = Module::factory()->create();
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);

        foreach (['modules', 'lessons', 'vocabularies', 'materials', 'exercises'] as $resource) {
            $response = $this->actingAs($admin)->get(route("superadmin.{$resource}.index"));
            $response->assertOk()->assertSee('name="order"', false);
        }

        $this->actingAs($admin)->post(route('superadmin.vocabularies.store'), [
            'lesson_id' => $lesson->id,
            'category' => 'Ordered words',
            'order' => 12,
            'items' => [
                ['term' => 'second', 'details' => null, 'order' => 2],
                ['term' => 'first', 'details' => null, 'order' => 1],
            ],
        ])->assertRedirect(route('superadmin.vocabularies.index'));

        $vocabulary = Vocabulary::where('category', 'Ordered words')->firstOrFail();
        $this->assertSame(12, $vocabulary->order);
        $this->assertSame(['first', 'second'], $vocabulary->items->pluck('term')->all());

        $this->actingAs($admin)->post(route('superadmin.materials.store'), [
            'lesson_id' => $lesson->id,
            'type' => 'Teks',
            'order' => 13,
            'items' => [
                ['title' => 'Second', 'description' => 'Second item', 'order' => 2],
                ['title' => 'First', 'description' => 'First item', 'order' => 1],
            ],
        ])->assertRedirect(route('superadmin.materials.index'));

        $material = Material::where('lesson_id', $lesson->id)->where('type', 'Teks')->firstOrFail();
        $this->assertSame(13, $material->order);
        $this->assertSame(['First', 'Second'], $material->items->pluck('title')->all());
    }

    public function test_create_defaults_order_to_zero_and_update_omission_preserves_existing_order(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($admin)->post(route('superadmin.modules.store'), [
            'title' => 'Default order module',
            'description' => 'Created without an explicit order.',
            'level' => 'beginner',
            'is_published' => true,
        ])->assertRedirect(route('superadmin.modules.index'));

        $module = Module::where('title', 'Default order module')->firstOrFail();
        $this->assertSame(0, $module->order);

        $this->actingAs($admin)->post(route('superadmin.lessons.store'), [
            'title' => 'Default order lesson',
            'slug' => 'default-order-lesson',
            'module_id' => $module->id,
        ])->assertRedirect(route('superadmin.lessons.index'));

        $lesson = Lesson::where('slug', 'default-order-lesson')->firstOrFail();
        $this->assertSame(0, $lesson->order);

        $this->actingAs($admin)->post(route('superadmin.vocabularies.store'), [
            'lesson_id' => $lesson->id,
            'category' => 'Default order vocabulary',
            'items' => [['term' => 'term', 'details' => 'details']],
        ])->assertRedirect(route('superadmin.vocabularies.index'));

        $vocabulary = Vocabulary::where('category', 'Default order vocabulary')->firstOrFail();
        $vocabularyItem = $vocabulary->items()->firstOrFail();
        $this->assertSame(0, $vocabulary->order);
        $this->assertSame(0, $vocabularyItem->order);

        $this->actingAs($admin)->post(route('superadmin.materials.store'), [
            'lesson_id' => $lesson->id,
            'type' => 'Teks',
            'items' => [['title' => 'Default item', 'description' => 'Default description']],
        ])->assertRedirect(route('superadmin.materials.index'));

        $material = Material::where('lesson_id', $lesson->id)->where('type', 'Teks')->firstOrFail();
        $materialItem = $material->items()->firstOrFail();
        $this->assertSame(0, $material->order);
        $this->assertSame(0, $materialItem->order);

        $module->update(['order' => 11]);
        $lesson->update(['order' => 12]);
        $vocabulary->update(['order' => 13]);
        $vocabularyItem->update(['order' => 14]);
        $material->update(['order' => 15]);
        $materialItem->update(['order' => 16]);

        $this->actingAs($admin)->put(route('superadmin.modules.update', $module), [
            'title' => $module->title,
            'description' => $module->description,
            'level' => $module->level,
            'is_published' => true,
        ])->assertRedirect(route('superadmin.modules.index'));

        $this->actingAs($admin)->put(route('superadmin.lessons.update', $lesson), [
            'title' => $lesson->title,
            'slug' => $lesson->slug,
            'module_id' => $module->id,
        ])->assertRedirect(route('superadmin.lessons.index'));

        $this->actingAs($admin)->put(route('superadmin.vocabularies.update', $vocabulary), [
            'lesson_id' => $lesson->id,
            'category' => $vocabulary->category,
            'items' => [[
                'id' => $vocabularyItem->id,
                'term' => $vocabularyItem->term,
                'details' => $vocabularyItem->details,
            ]],
        ])->assertRedirect(route('superadmin.vocabularies.index'));

        $this->actingAs($admin)->put(route('superadmin.materials.update', $material), [
            'lesson_id' => $lesson->id,
            'items' => [[
                'id' => $materialItem->id,
                'title' => $materialItem->title,
                'description' => $materialItem->description,
            ]],
        ])->assertRedirect(route('superadmin.materials.index'));

        $this->assertSame(11, $module->fresh()->order);
        $this->assertSame(12, $lesson->fresh()->order);
        $this->assertSame(13, $vocabulary->fresh()->order);
        $this->assertSame(14, $vocabularyItem->fresh()->order);
        $this->assertSame(15, $material->fresh()->order);
        $this->assertSame(16, $materialItem->fresh()->order);
    }
}
