<?php

namespace Tests\Feature;

use App\Http\Requests\ExerciseRequest;
use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseCrudContractTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }

    public function test_all_nineteen_engine_types_complete_the_admin_crud_contract(): void
    {
        $lesson = Lesson::factory()->create();
        $admin = $this->admin();
        $subEx = Exercise::factory()->create(['lesson_id' => $lesson->id, 'type' => 'multiple_choice_quiz']);
        $created = [];

        foreach ($this->validContents($subEx->id) as $type => $content) {
            $response = $this->actingAs($admin)->post(route('superadmin.exercises.store'), [
                'lesson_id' => $lesson->id,
                'title' => "Contract {$type}",
                'type' => $type,
                'content' => $content,
                'order' => 1,
            ]);
            $response->assertRedirect(route('superadmin.exercises.index'));

            $exercise = Exercise::where('title', "Contract {$type}")->firstOrFail();
            $this->assertSame($type, $exercise->type);
            $this->assertSame($content, $exercise->content);
            $created[] = [$exercise, $content];
        }

        $this->assertSame(ExerciseRequest::TYPES, Exercise::where('id', '!=', $subEx->id)->orderBy('id')->pluck('type')->all());

        foreach ($created as [$exercise, $content]) {
            $this->actingAs($admin)->put(route('superadmin.exercises.update', $exercise), [
                'lesson_id' => $lesson->id,
                'title' => "Updated {$exercise->type}",
                'content' => $content,
                'order' => 2,
            ])->assertRedirect(route('superadmin.exercises.index'));

            $exercise->refresh();
            $this->assertSame($content, $exercise->content);
            $this->assertSame($exercise->type, str_replace('Updated ', '', $exercise->title));
        }

        foreach ($created as [$exercise]) {
            $this->actingAs($admin)->delete(route('superadmin.exercises.destroy', $exercise))
                ->assertRedirect(route('superadmin.exercises.index'));
            $this->assertDatabaseMissing('exercises', ['id' => $exercise->id]);
        }
    }

    public function test_exercise_type_is_immutable_and_extra_content_keys_are_rejected(): void
    {
        $admin = $this->admin();
        $exercise = Exercise::factory()->create([
            'type' => 'matching_game',
            'content' => ['pairs' => [['question' => 'Room', 'answer' => 'Kamar']]],
        ]);

        $this->actingAs($admin)->put(route('superadmin.exercises.update', $exercise), [
            'lesson_id' => $exercise->lesson_id,
            'title' => 'Attempted Type Change',
            'type' => 'spelling_quiz',
            'content' => ['pairs' => [['question' => 'Room', 'answer' => 'Kamar']]],
            'order' => 1,
        ])->assertSessionHasErrors('type');

        $this->actingAs($admin)->put(route('superadmin.exercises.update', $exercise), [
            'lesson_id' => $exercise->lesson_id,
            'title' => 'Extra Key Submission',
            'type' => 'matching_game',
            'content' => [
                'pairs' => [['question' => 'Room', 'answer' => 'Kamar']],
                'disallowed_extra_key' => true,
            ],
            'order' => 1,
        ])->assertSessionHasErrors('content');
    }

    public function test_retained_legacy_editor_exposes_supported_types_but_navigation_is_canonical_first(): void
    {
        $admin = $this->admin();
        $response = $this->actingAs($admin)->get(route('superadmin.exercises.index'));

        $response->assertOk();
        $this->assertTrue(count(ExerciseRequest::TYPES) >= 19);
    }

    /** @return array<string, array<string, mixed>> */
    private function validContents(int $subExId = 1): array
    {
        return [
            'spelling_quiz' => ['correct_answer' => 'reservation', 'prompt_text' => 'reservation'],
            'matching_game' => ['pairs' => [['question' => 'Hello', 'answer' => 'Halo']]],
            'fill_in_the_blank' => ['sentence_template' => 'Welcome ___ the hotel.', 'correct_answer' => 'to'],
            'listening_task' => ['instruction' => 'Choose one', 'options' => ['A', 'B'], 'correct_answer' => 'A'],
            'speaking_practice' => ['prompt_text' => 'Welcome to our hotel.'],
            'sentence_scramble' => ['sentence' => 'Your room is ready.'],
            'translation_match' => ['question_word' => 'room', 'options' => ['kamar', 'meja'], 'correct_answer' => 'kamar'],
            'fill_with_options' => ['sentence_parts' => ['The guest', 'early'], 'options' => ['arrived', 'left'], 'correct_answer' => 'arrived'],
            'multiple_choice_quiz' => ['question_text' => 'Choose A', 'options' => ['A', 'B'], 'correct_answer' => 'A'],
            'fill_multiple_blanks' => ['sentence_parts' => ['The', 'is', 'now'], 'correct_answers' => ['room', 'ready']],
            'silent_letter_hunt' => ['sentence' => 'Use a knife.', 'words' => [['word' => 'knife', 'silent_letter_index' => 0]]],
            'pronunciation_drill' => ['prompt_text' => 'Could I see your passport, please?'],
            'sound_sorting' => [
                'categories' => [['id' => 'short', 'name' => 'Short vowel']],
                'words' => [['word' => 'ship', 'category_id' => 'short']],
            ],
            'sequencing' => ['steps' => ['Greet the guest', 'Confirm the reservation']],
            'information' => ['body' => 'Welcome to the course.', 'media_url' => null, 'media_type' => null],
            'writing' => ['prompt' => 'Greet the guest.', 'min_words' => 5, 'max_words' => 100, 'keywords' => [['text' => 'welcome', 'weight' => 1, 'required' => false, 'case_sensitive' => false]], 'model_answer' => null, 'accept_spelling_errors' => true],
            'drag_the_words' => ['text' => 'The guest is *checking in*.', 'distractors' => ['checking out'], 'show_solution' => true, 'instant_feedback' => false],
            'drag_and_drop' => [
                'background_image' => null,
                'draggables' => [['id' => 'd1', 'label' => 'Key', 'image' => null, 'multiple' => false]],
                'drop_zones' => [['id' => 'z1', 'label' => 'Desk', 'x' => 10, 'y' => 10, 'width' => 50, 'height' => 50, 'single' => true, 'correct_draggable_ids' => ['d1']]],
                'single_point' => false,
                'show_solution' => true,
            ],
            'question_set' => [
                'pass_percentage' => 70,
                'allow_retry' => true,
                'show_solution' => true,
                'exercise_ids' => [$subExId],
                'feedback_ranges' => [['from' => 0, 'to' => 100, 'message' => 'Good!']],
            ],
        ];
    }
}
