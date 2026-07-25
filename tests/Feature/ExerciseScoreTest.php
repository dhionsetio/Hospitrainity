<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseScoreTest extends TestCase
{
    use RefreshDatabase;

    private function learner(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }

    private function createPublishedExercise(string $type = 'multiple_choice_quiz', array $content = []): Exercise
    {
        $module = Module::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);

        $defaultContent = match ($type) {
            'information' => ['body' => 'Welcome to the course.'],
            'writing' => ['prompt' => 'Greet the guest.', 'keywords' => [['text' => 'welcome', 'weight' => 1]]],
            'drag_the_words' => ['text' => 'The guest is *checking in*.'],
            'drag_and_drop' => [
                'draggables' => [['id' => 'd1', 'label' => 'Key']],
                'drop_zones' => [['id' => 'z1', 'label' => 'Desk', 'x' => 10, 'y' => 10, 'width' => 50, 'height' => 50, 'correct_draggable_ids' => ['d1']]],
            ],
            default => ['question_text' => 'Q?', 'correct_answer' => 'A', 'options' => ['A', 'B']],
        };

        return Exercise::factory()->create([
            'lesson_id' => $lesson->id,
            'type' => $type,
            'content' => array_merge($defaultContent, $content),
        ]);
    }

    public function test_learner_can_save_exercise_score(): void
    {
        $user = $this->learner();
        $exercise = $this->createPublishedExercise();

        $response = $this->actingAs($user)->postJson(route('scores.store'), [
            'exercise_id' => $exercise->id,
            'score' => 1,
            'max_score' => 1,
            'response_data' => ['selected' => 'A'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('exercise_scores', [
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'score' => 1,
            'max_score' => 1,
        ]);
    }

    public function test_score_exceeding_max_score_is_rejected(): void
    {
        $user = $this->learner();
        $exercise = $this->createPublishedExercise();

        $response = $this->actingAs($user)->postJson(route('scores.store'), [
            'exercise_id' => $exercise->id,
            'score' => 10,
            'max_score' => 5,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['score']);
    }

    public function test_score_for_unpublished_exercise_is_rejected(): void
    {
        $user = $this->learner();
        $module = Module::factory()->create(['is_published' => false]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);
        $exercise = Exercise::factory()->create(['lesson_id' => $lesson->id]);

        $response = $this->actingAs($user)->postJson(route('scores.store'), [
            'exercise_id' => $exercise->id,
            'score' => 1,
            'max_score' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['exercise_id']);
    }

    public function test_admin_can_create_new_lumi_exercise_types(): void
    {
        $admin = $this->admin();
        $module = Module::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);

        // 1. Information type
        $res1 = $this->actingAs($admin)->post(route('superadmin.exercises.store'), [
            'lesson_id' => $lesson->id,
            'title' => 'Intro Info',
            'type' => 'information',
            'content' => [
                'body' => 'Welcome to hotel management.',
            ],
        ]);
        $res1->assertRedirect();
        $this->assertDatabaseHas('exercises', ['title' => 'Intro Info', 'type' => 'information']);

        // 2. Writing type
        $res2 = $this->actingAs($admin)->post(route('superadmin.exercises.store'), [
            'lesson_id' => $lesson->id,
            'title' => 'Check-in Essay',
            'type' => 'writing',
            'content' => [
                'prompt' => 'Describe guest check-in.',
                'min_words' => 5,
                'keywords' => [
                    ['text' => 'reservation', 'weight' => 2],
                ],
            ],
        ]);
        $res2->assertRedirect();
        $this->assertDatabaseHas('exercises', ['title' => 'Check-in Essay', 'type' => 'writing']);

        // 3. Drag the words type
        $res3 = $this->actingAs($admin)->post(route('superadmin.exercises.store'), [
            'lesson_id' => $lesson->id,
            'title' => 'Drag Words',
            'type' => 'drag_the_words',
            'content' => [
                'text' => 'The guest is *checking in*.',
                'distractors' => ['checking out'],
            ],
        ]);
        $res3->assertRedirect();
        $this->assertDatabaseHas('exercises', ['title' => 'Drag Words', 'type' => 'drag_the_words']);

        // 4. Drag and drop image type
        $res4 = $this->actingAs($admin)->post(route('superadmin.exercises.store'), [
            'lesson_id' => $lesson->id,
            'title' => 'Drag to Target',
            'type' => 'drag_and_drop',
            'content' => [
                'draggables' => [['id' => 'd1', 'label' => 'Keycard']],
                'drop_zones' => [[
                    'id' => 'z1',
                    'label' => 'Reader',
                    'x' => 10,
                    'y' => 10,
                    'width' => 40,
                    'height' => 40,
                    'correct_draggable_ids' => ['d1'],
                ]],
            ],
        ]);
        $res4->assertRedirect();
        $this->assertDatabaseHas('exercises', ['title' => 'Drag to Target', 'type' => 'drag_and_drop']);
    }
}
