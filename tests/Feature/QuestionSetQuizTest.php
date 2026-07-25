<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionSetQuizTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }

    public function test_superadmin_can_create_question_set_quiz(): void
    {
        $lesson = Lesson::factory()->create();
        $admin = $this->superadmin();

        $subEx1 = Exercise::factory()->create(['lesson_id' => $lesson->id, 'type' => 'multiple_choice_quiz']);
        $subEx2 = Exercise::factory()->create(['lesson_id' => $lesson->id, 'type' => 'fill_in_the_blank']);

        $response = $this->actingAs($admin)->post(route('superadmin.exercises.store'), [
            'lesson_id' => $lesson->id,
            'title' => 'End of Module Quiz',
            'type' => 'question_set',
            'content' => [
                'exercise_ids' => [$subEx1->id, $subEx2->id],
                'pass_percentage' => 75,
                'feedback_ranges' => [
                    ['from' => 0, 'to' => 49, 'message' => 'Keep practicing!'],
                    ['from' => 50, 'to' => 74, 'message' => 'Good effort!'],
                    ['from' => 75, 'to' => 100, 'message' => 'Excellent practice!'],
                ],
                'allow_retry' => true,
                'show_solution' => true,
            ],
            'order' => 1,
        ]);

        $response->assertRedirect(route('superadmin.exercises.index'));
        $this->assertDatabaseHas('exercises', [
            'title' => 'End of Module Quiz',
            'type' => 'question_set',
        ]);
    }

    public function test_question_set_with_invalid_pass_percentage_is_rejected(): void
    {
        $lesson = Lesson::factory()->create();
        $admin = $this->superadmin();

        $response = $this->actingAs($admin)->post(route('superadmin.exercises.store'), [
            'lesson_id' => $lesson->id,
            'title' => 'Invalid Quiz',
            'type' => 'question_set',
            'content' => [
                'exercise_ids' => [1],
                'pass_percentage' => 150,
            ],
            'order' => 1,
        ]);

        $response->assertSessionHasErrors('content.pass_percentage');
    }
}
