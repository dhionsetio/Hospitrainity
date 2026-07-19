<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Material;
use App\Models\Module;
use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearnerContentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_published_learner_content_routes_render(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $module = Module::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->for($module)->create();
        $vocabulary = Vocabulary::factory()->for($lesson)->create();
        $material = Material::factory()->for($lesson)->create();

        $routes = [
            route('modules.show', $module),
            route('lessons.show', $lesson),
            route('lessons.practice', [$lesson, $vocabulary]),
            route('lessons.material.show', [$lesson, $material]),
            route('lessons.exercise.practice', $lesson),
        ];

        foreach ($routes as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }

    public function test_nested_vocabulary_binding_rejects_a_different_lesson(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $requestedLesson = Lesson::factory()->create();
        $foreignVocabulary = Vocabulary::factory()->create();

        $this->actingAs($user)
            ->get(route('lessons.practice', [$requestedLesson, $foreignVocabulary]))
            ->assertNotFound();
    }

    public function test_nested_material_binding_rejects_a_different_lesson(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $requestedLesson = Lesson::factory()->create();
        $foreignMaterial = Material::factory()->create();

        $this->actingAs($user)
            ->get(route('lessons.material.show', [$requestedLesson, $foreignMaterial]))
            ->assertNotFound();
    }

    public function test_unpublished_curriculum_is_hidden_from_every_learner_content_route(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $module = Module::factory()->draft()->create();
        $lesson = Lesson::factory()->for($module)->create();
        $vocabulary = Vocabulary::factory()->for($lesson)->create();
        $material = Material::factory()->for($lesson)->create();

        $routes = [
            route('modules.show', $module),
            route('lessons.show', $lesson),
            route('lessons.practice', [$lesson, $vocabulary]),
            route('lessons.material.show', [$lesson, $material]),
            route('lessons.exercise.practice', $lesson),
        ];

        foreach ($routes as $url) {
            $this->actingAs($user)->get($url)->assertNotFound();
        }
    }
}
