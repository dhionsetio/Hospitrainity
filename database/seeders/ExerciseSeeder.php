<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\Lesson;
use Illuminate\Database\Seeder;

/**
 * Retired legacy exercise fixtures retained for migration/audit evidence.
 * DatabaseSeeder does not call this class; canonical activities are imported
 * losslessly and are never coerced into these legacy exercise formats.
 */
class ExerciseSeeder extends Seeder
{
    /**
     * Database ids of every lesson, in canonical order (module order, then
     * lesson order). Index 0 = the 1st lesson, index 1 = the 2nd, etc. Built
     * once in run() and used by store() to translate 1-based ordinals into real
     * ids so exercises never attach to the wrong lesson if ids drift.
     */
    private array $lessonIds = [];

    public function run(): void
    {
        $this->lessonIds = Lesson::orderBy('id')->pluck('id')->all();

        // Example for lesson 1.
        $this->store([
            'lesson_id' => 1,
            'title' => 'Matching Game: Sounds',
            'type' => 'matching_game',
            'content' => [
                'pairs' => [
                    ['question' => 'G', 'answer' => '/dʒiː/'],
                    ['question' => 'H', 'answer' => '/eɪtʃ/'],
                    ['question' => 'J', 'answer' => '/dʒeɪ/'],
                    ['question' => 'W', 'answer' => '/ˈdʌbəl.juː/'],
                ],
            ],
        ]);

        $this->store([
            'lesson_id' => 1,
            'title' => 'Pronunciation Drill',
            'type' => 'pronunciation_drill',
            'content' => [
                'prompt_text' => 'The quick brown fox jumps over the lazy dog.',
            ],
        ]);

        $this->store([
            'lesson_id' => 1,
            'title' => 'Silent Letter Hunt',
            'type' => 'silent_letter_hunt',
            'content' => [
                'sentence' => 'The knight knows how to write with a pen.',
                'words' => [
                    ['word' => 'knight', 'silent_letter_index' => 0],
                    ['word' => 'knows', 'silent_letter_index' => 0],
                    ['word' => 'write', 'silent_letter_index' => 0],
                ],
            ],
        ]);

        $this->store([
            'lesson_id' => 1,
            'title' => 'Spelling Quiz: Hospitality',
            'type' => 'spelling_quiz',
            'content' => [
                'prompt_text' => 'reservation',
                'correct_answer' => 'reservation',
            ],
        ]);

        $this->store([
            'lesson_id' => 1,
            'title' => 'Sound Sorting',
            'type' => 'sound_sorting',
            'content' => [
                'categories' => [
                    ['name' => '/r/ sound', 'id' => 'r_sound'],
                    ['name' => '/l/ sound', 'id' => 'l_sound'],
                ],
                'words' => [
                    ['word' => 'room', 'category_id' => 'r_sound'],
                    ['word' => 'lobby', 'category_id' => 'l_sound'],
                    ['word' => 'reception', 'category_id' => 'r_sound'],
                    ['word' => 'local', 'category_id' => 'l_sound'],
                ],
            ],
        ]);

        $this->store([
            'lesson_id' => 2,
            'title' => 'Fill in the Blank',
            'type' => 'fill_in_the_blank',
            'content' => [
                'sentence_parts' => ['', 'morning! My name is Rina.'],
                'correct_answer' => 'Good',
            ],
        ]);

        $this->store([
            'lesson_id' => 2,
            'title' => 'Sentence Scramble',
            'type' => 'sentence_scramble',
            'content' => [
                'sentence' => 'Hi, welcome to our hotel!',
            ],
        ]);

        $this->store([
            'lesson_id' => 2,
            'title' => 'Listening Task',
            'type' => 'listening_task',
            'content' => [
                'instruction' => 'Listen to the phrase and choose the correct option.',
                'options' => ['Good afternoon', 'Good evening'],
                'correct_answer' => 'Good evening',
            ],
        ]);

        $this->store([
            'lesson_id' => 2,
            'title' => 'Translation Match',
            'type' => 'translation_match',
            'content' => [
                'question_word' => 'Selamat datang',
                'options' => ['Welcome', 'Thank You', 'Good Morning'],
                'correct_answer' => 'Welcome',
            ],
        ]);

        $this->store([
            'lesson_id' => 2,
            'title' => 'Speaking Practice',
            'type' => 'pronunciation_drill',
            'content' => [
                'prompt_text' => 'I’m here to assist you',
            ],
        ]);

        $this->store([
            'lesson_id' => 3,
            'title' => 'Minimal Pair Quiz',
            'type' => 'listening_task',
            'content' => [
                'instruction' => 'Listen to the phrase and choose the correct option.',
                'options' => ['Ship', 'Sheep'],
                'correct_answer' => 'Ship',
            ],
        ]);

        $this->store([
            'lesson_id' => 3,
            'title' => 'Sentence Practice',
            'type' => 'pronunciation_drill',
            'content' => [
                'prompt_text' => 'The bill is ready',
            ],
        ]);

        $this->store([
            'lesson_id' => 3,
            'title' => 'Contextual Pair Game',
            'type' => 'fill_with_options',
            'content' => [
                'sentence_parts' => ['Please pay the ', '.'],
                'options' => ['bill', 'bell'],
                'correct_answer' => 'bill',
            ],
        ]);

        $this->store([
            'lesson_id' => 4,
            'title' => 'Fill in the Blank',
            'type' => 'fill_in_the_blank',
            'content' => [
                'sentence_parts' => ['We', 'breakfast every day.'],
                'correct_answer' => 'provide',
            ],
        ]);

        $this->store([
            'lesson_id' => 4,
            'title' => 'Sentence Scramble',
            'type' => 'sentence_scramble',
            'content' => [
                'sentence' => 'I work at the hotel',
            ],
        ]);

        $this->store([
            'lesson_id' => 4,
            'title' => 'Grammar Quiz',
            'type' => 'multiple_choice_quiz',
            'content' => [
                'question_text' => 'Choose the correct verb: "She ___ at reception."',
                'options' => ['work', 'works'],
                'correct_answer' => 'works',
            ],
        ]);

        $this->store([
            'lesson_id' => 4,
            'title' => 'Speaking Drill',
            'type' => 'pronunciation_drill',
            'content' => [
                'prompt_text' => 'They offer free parking',
            ],
        ]);

        $this->store([
            'lesson_id' => 4,
            'title' => 'Job Duty Exercise',
            'type' => 'multiple_choice_quiz',
            'content' => [
                'question_text' => 'I ___ the lobby',
                'options' => ['ask', 'clean', 'write'],
                'correct_answer' => 'clean',
            ],
        ]);

        $this->store([
            'lesson_id' => 4,
            'title' => 'Agreement Game',
            'type' => 'multiple_choice_quiz',
            'content' => [
                'question_text' => 'The staff',
                'options' => ['help', 'helps'],
                'correct_answer' => 'helps',
            ],
        ]);

        $this->store([
            'lesson_id' => 5,
            'title' => 'Fill in the Blank',
            'type' => 'fill_in_the_blank',
            'content' => [
                'sentence_parts' => ['He', 'at 10 AM.'],
                'correct_answer' => 'arrived',
            ],
        ]);

        $this->store([
            'lesson_id' => 5,
            'title' => 'Quiz',
            'type' => 'multiple_choice_quiz',
            'content' => [
                'question_text' => 'Did they ___ last night',
                'options' => ['stayed', 'stay'],
                'correct_answer' => 'stay',
            ],
        ]);

        $this->store([
            'lesson_id' => 5,
            'title' => 'Story Completion',
            'type' => 'fill_multiple_blanks',
            'content' => [
                'sentence_parts' => ['Yesterday, the guest ', ' and ', ' a key.'],
                'correct_answers' => ['arrived', 'took'],
            ],
        ]);

        $this->store([
            'lesson_id' => 5,
            'title' => 'Irregular Verb Quiz',
            'type' => 'multiple_choice_quiz',
            'content' => [
                'question_text' => 'Did they ___ last night',
                'options' => ['went', 'want'],
                'correct_answer' => 'went',
            ],
        ]);

        $this->store([
            'lesson_id' => 6,
            'title' => 'Number Match',
            'type' => 'multiple_choice_quiz',
            'content' => [
                'question_text' => '15',
                'options' => ['fifteen', 'fourteen', 'sixteen'],
                'correct_answer' => 'fifteen',
            ],
        ]);

        $this->store([
            'lesson_id' => 6,
            'title' => 'Time Quiz',
            'type' => 'multiple_choice_quiz',
            'content' => [
                'question_text' => 'What’s 3:45?',
                'options' => ['quarter to four', 'four o clock', 'quarter to five'],
                'correct_answer' => 'quarter to four',
            ],
        ]);

        $this->store([
            'lesson_id' => 6,
            'title' => 'Listening Task',
            'type' => 'spelling_quiz',
            'content' => [
                'prompt_text' => 'nine thirty',
                'correct_answer' => 'nine thirty',
            ],
        ]);

        $this->store([
            'lesson_id' => 6,
            'title' => 'Sentence Building',
            'type' => 'multiple_choice_quiz',
            'content' => [
                'question_text' => 'Dinner is at ____',
                'options' => ['7 PM', '3 PM', '10 AM'],
                'correct_answer' => '7 PM',
            ],
        ]);

        $this->store([
            'lesson_id' => 6,
            'title' => 'Room Number Game',
            'type' => 'spelling_quiz',
            'content' => [
                'prompt_text' => 'Room 405',
                'correct_answer' => 'Room 405',
            ],
        ]);

        $this->store([
            'lesson_id' => 7,
            'title' => 'Fill in the Blank',
            'type' => 'fill_in_the_blank',
            'content' => [
                'sentence_parts' => ['', 'you have a reservation?" '],
                'correct_answer' => 'do',
            ],
        ]);

        $this->store([
            'lesson_id' => 6,
            'title' => 'Question Creation',
            'type' => 'multiple_choice_quiz',
            'content' => [
                'question_text' => 'When did you ___?',
                'options' => ['arrive', 'home', '10 AM'],
                'correct_answer' => 'arrive',
            ],
        ]);

        $this->store([
            'lesson_id' => 6,
            'title' => 'Guest Response Game',
            'type' => 'multiple_choice_quiz',
            'content' => [
                'question_text' => 'How many nights?',
                'options' => ['two nights', 'tomorrow', 'Im good'],
                'correct_answer' => 'two nights',
            ],
        ]);

        // Module 1 -> Lesson 1
        $lessonId = 9; // Resolve to the canonical "Greetings and Welcoming Guests" lesson below.
        $this->store(['lesson_id' => $lessonId, 'title' => 'Matching Game: Greetings', 'type' => 'matching_game', 'content' => ['pairs' => [['question' => 'Welcome back!', 'answer' => 'Returning guest.']]]]);
        $this->store(['lesson_id' => $lessonId, 'title' => 'Fill-in-the-Blank', 'type' => 'fill_in_the_blank', 'content' => ['sentence_parts' => ['', ' to Youkata Stay Hotel!'], 'correct_answer' => 'Welcome']]);
        $this->store(['lesson_id' => $lessonId, 'title' => 'Listening Task', 'type' => 'listening_task', 'content' => [
            'instruction' => 'Listen to the greeting and choose the correct text.',
            'options' => ['Welcome back!', 'Enjoy your stay!', 'Good morning!'],
            'correct_answer' => 'Welcome back!',
        ]]);

        // Module 2 -> Lesson 1
        $lessonId = 11; // Resolve to the canonical "Verifying Reservations..." lesson below.
        $this->store(['lesson_id' => $lessonId, 'title' => 'Fill-in-the-Blank', 'type' => 'fill_in_the_blank', 'content' => ['sentence_parts' => ['Could you please', 'me your passport?'], 'correct_answer' => 'show']]);
        $this->store(['lesson_id' => $lessonId, 'title' => 'Sequencing', 'type' => 'sequencing', 'content' => ['steps' => ['Greet', 'Verify Reservation', 'Collect ID', 'Confirm Booking']]]);
    }

    /**
     * Idempotently upsert one exercise.
     *
     * The incoming 'lesson_id' is a 1-based CANONICAL ordinal (1 = the first
     * lesson, 2 = the second, ...), not a hard database id. It is translated
     * through $this->lessonIds so exercises always attach to the correct lesson
     * even if the underlying auto-increment ids ever drift. Keyed on
     * (lesson_id, title) so re-running the seeder never duplicates rows.
     */
    private function store(array $data): void
    {
        $ordinal = (int) $data['lesson_id'];
        $data['lesson_id'] = $this->lessonIds[$ordinal - 1] ?? $ordinal;

        Exercise::updateOrCreate(
            ['lesson_id' => $data['lesson_id'], 'title' => $data['title']],
            $data
        );
    }
}
