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

    public function test_all_fourteen_engine_types_complete_the_admin_crud_contract(): void
    {
        $lesson = Lesson::factory()->create();
        $admin = $this->admin();
        $created = [];

        foreach ($this->validContents() as $type => $content) {
            $this->actingAs($admin)->post(route('superadmin.exercises.store'), [
                'lesson_id' => $lesson->id,
                'title' => "Contract {$type}",
                'type' => $type,
                'content' => $content,
                'order' => 1,
            ])->assertRedirect(route('superadmin.exercises.index'));

            $exercise = Exercise::where('title', "Contract {$type}")->firstOrFail();
            $this->assertSame($type, $exercise->type);
            $this->assertSame($content, $exercise->content);
            $created[] = [$exercise, $content];
        }

        $this->assertSame(ExerciseRequest::TYPES, Exercise::orderBy('id')->pluck('type')->all());

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
            $this->assertSame(2, $exercise->order);

            $this->actingAs($admin)
                ->delete(route('superadmin.exercises.destroy', $exercise))
                ->assertRedirect(route('superadmin.exercises.index'));
            $this->assertDatabaseMissing('exercises', ['id' => $exercise->id]);
        }

        $this->assertDatabaseCount('exercises', 0);
    }

    public function test_exercise_type_is_immutable_and_extra_content_keys_are_rejected(): void
    {
        $exercise = Exercise::factory()->create([
            'type' => 'multiple_choice_quiz',
            'content' => [
                'question_text' => 'Question?',
                'options' => ['A', 'B'],
                'correct_answer' => 'A',
            ],
        ]);

        $this->actingAs($this->admin())->put(route('superadmin.exercises.update', $exercise), [
            'lesson_id' => $exercise->lesson_id,
            'title' => 'Changed',
            'type' => 'matching_game',
            'content' => ['pairs' => [['question' => 'A', 'answer' => 'B']]],
        ])->assertSessionHasErrors('type');

        $this->actingAs($this->admin())->put(route('superadmin.exercises.update', $exercise), [
            'lesson_id' => $exercise->lesson_id,
            'title' => 'Changed',
            'type' => 'multiple_choice_quiz',
            'content' => [
                'question_text' => 'Question?',
                'options' => ['A', 'B'],
                'correct_answer' => 'A',
                'pairs' => [['question' => 'Injected', 'answer' => 'Inactive editor']],
            ],
        ])->assertSessionHasErrors('content');

        $this->assertSame('multiple_choice_quiz', $exercise->fresh()->type);
        $this->assertSame('Question?', $exercise->fresh()->content['question_text']);
    }

    public function test_retained_legacy_editor_exposes_supported_types_but_navigation_is_canonical_first(): void
    {
        $response = $this->actingAs($this->admin())->get(route('superadmin.exercises.index'));

        $response->assertOk()
            ->assertSee(route('superadmin.curriculum-exercises.index'), false)
            ->assertSee(route('superadmin.legacy-evidence.index'), false);
        foreach (ExerciseRequest::TYPES as $type) {
            $response->assertSee('value="'.$type.'"', false);
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function validContents(): array
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
        ];
    }
}
