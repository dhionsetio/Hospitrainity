<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Per-type content validation on the exercise admin endpoints. */
class ExerciseValidationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }

    public function test_unknown_type_is_rejected(): void
    {
        $lesson = Lesson::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('superadmin.exercises.store'), [
            'lesson_id' => $lesson->id,
            'title' => 'Bad type',
            'type' => 'not_a_real_type',
            'content' => ['correct_answer' => 'x'],
        ]);

        $response->assertSessionHasErrors('type');
        $this->assertDatabaseCount('exercises', 0);
    }

    public function test_multiple_choice_missing_options_is_rejected(): void
    {
        $lesson = Lesson::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('superadmin.exercises.store'), [
            'lesson_id' => $lesson->id,
            'title' => 'MCQ',
            'type' => 'multiple_choice_quiz',
            'content' => ['question_text' => 'Q?', 'correct_answer' => 'A'],
        ]);

        $response->assertSessionHasErrors('content.options');
        $this->assertDatabaseCount('exercises', 0);
    }

    public function test_valid_multiple_choice_is_created(): void
    {
        $lesson = Lesson::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('superadmin.exercises.store'), [
            'lesson_id' => $lesson->id,
            'title' => 'Grammar',
            'type' => 'multiple_choice_quiz',
            'content' => [
                'question_text' => 'She ___ here.',
                'options' => ['work', 'works'],
                'correct_answer' => 'works',
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('exercises', ['title' => 'Grammar', 'type' => 'multiple_choice_quiz']);
    }

    public function test_sound_sorting_requires_categories_and_words(): void
    {
        $lesson = Lesson::factory()->create();

        $bad = $this->actingAs($this->admin())->post(route('superadmin.exercises.store'), [
            'lesson_id' => $lesson->id,
            'title' => 'Sort',
            'type' => 'sound_sorting',
            'content' => ['categories' => [['id' => 'r', 'name' => '/r/']]],
        ]);
        $bad->assertSessionHasErrors('content.words');

        $ok = $this->actingAs($this->admin())->post(route('superadmin.exercises.store'), [
            'lesson_id' => $lesson->id,
            'title' => 'Sort OK',
            'type' => 'sound_sorting',
            'content' => [
                'categories' => [['id' => 'r', 'name' => '/r/'], ['id' => 'l', 'name' => '/l/']],
                'words' => [['word' => 'room', 'category_id' => 'r'], ['word' => 'local', 'category_id' => 'l']],
            ],
        ]);
        $ok->assertSessionHasNoErrors();
        $this->assertDatabaseHas('exercises', ['title' => 'Sort OK']);
    }

    public function test_fill_in_the_blank_accepts_admin_sentence_template(): void
    {
        $lesson = Lesson::factory()->create();

        // The admin form submits sentence_template (not sentence_parts); it must pass.
        $response = $this->actingAs($this->admin())->post(route('superadmin.exercises.store'), [
            'lesson_id' => $lesson->id,
            'title' => 'FITB',
            'type' => 'fill_in_the_blank',
            'content' => ['sentence_template' => '___ morning!', 'correct_answer' => 'Good'],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('exercises', ['title' => 'FITB']);
    }

    public function test_update_without_type_validates_against_existing_type(): void
    {
        $lesson = Lesson::factory()->create();
        $exercise = Exercise::factory()->create([
            'lesson_id' => $lesson->id,
            'type' => 'multiple_choice_quiz',
            'content' => ['question_text' => 'Q', 'options' => ['a', 'b'], 'correct_answer' => 'a'],
        ]);

        // The edit form disables the type <select>, so `type` is not submitted.
        // Malformed content for the existing type must still be rejected.
        $bad = $this->actingAs($this->admin())->put(route('superadmin.exercises.update', $exercise), [
            'lesson_id' => $lesson->id,
            'title' => 'Edited',
            'content' => ['question_text' => 'Q only'],
        ]);
        $bad->assertSessionHasErrors('content.options');

        // A valid edit (still no type in the payload) must succeed.
        $ok = $this->actingAs($this->admin())->put(route('superadmin.exercises.update', $exercise), [
            'lesson_id' => $lesson->id,
            'title' => 'Edited OK',
            'content' => ['question_text' => 'Q', 'options' => ['a', 'b', 'c'], 'correct_answer' => 'c'],
        ]);
        $ok->assertSessionHasNoErrors();
        $this->assertDatabaseHas('exercises', ['id' => $exercise->id, 'title' => 'Edited OK']);
    }

    public function test_renderer_dependent_content_relationships_are_enforced(): void
    {
        $lesson = Lesson::factory()->create();
        $admin = $this->admin();

        $blankMismatch = $this->actingAs($admin)->post(route('superadmin.exercises.store'), [
            'lesson_id' => $lesson->id,
            'title' => 'Mismatched blanks',
            'type' => 'fill_multiple_blanks',
            'content' => [
                'sentence_parts' => ['The ', ' is open.'],
                'correct_answers' => ['front', 'desk'],
            ],
        ]);
        $blankMismatch->assertSessionHasErrors('content.sentence_parts');

        $invalidIndex = $this->actingAs($admin)->post(route('superadmin.exercises.store'), [
            'lesson_id' => $lesson->id,
            'title' => 'Invalid silent index',
            'type' => 'silent_letter_hunt',
            'content' => [
                'sentence' => 'Use a knife.',
                'words' => [['word' => 'knife', 'silent_letter_index' => 5]],
            ],
        ]);
        $invalidIndex->assertSessionHasErrors('content.words.0.silent_letter_index');

        $this->assertDatabaseCount('exercises', 0);
    }

    public function test_spelling_uses_explicit_prompt_text_and_only_existing_public_audio(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('curriculum/exercises/prompt.wav', 'test-audio');
        $lesson = Lesson::factory()->create();
        $admin = $this->admin();
        $base = [
            'lesson_id' => $lesson->id,
            'title' => 'Spelling contract',
            'type' => 'spelling_quiz',
        ];

        $missingPrompt = $this->actingAs($admin)->post(route('superadmin.exercises.store'), $base + [
            'content' => ['correct_answer' => 'reservation', 'audio_url' => 'reservation'],
        ]);
        $missingPrompt->assertSessionHasErrors(['content.prompt_text', 'content.audio_url']);

        $missingFile = $this->actingAs($admin)->post(route('superadmin.exercises.store'), $base + [
            'content' => [
                'correct_answer' => 'reservation',
                'prompt_text' => 'reservation',
                'audio_url' => '/storage/curriculum/exercises/missing.mp3',
            ],
        ]);
        $missingFile->assertSessionHasErrors('content.audio_url');

        $valid = $this->actingAs($admin)->post(route('superadmin.exercises.store'), $base + [
            'content' => [
                'correct_answer' => 'reservation',
                'prompt_text' => 'reservation',
                'audio_url' => '/storage/curriculum/exercises/prompt.wav',
            ],
        ]);
        $valid->assertRedirect(route('superadmin.exercises.index'));
        $this->assertDatabaseCount('exercises', 1);
    }
}
