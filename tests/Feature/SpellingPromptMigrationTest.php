<?php

namespace Tests\Feature;

use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpellingPromptMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_text_and_audio_values_migrate_and_roll_back_without_guessing(): void
    {
        $textPrompt = Exercise::factory()->create([
            'type' => 'spelling_quiz',
            'content' => ['audio_url' => 'Room 405', 'correct_answer' => 'Room 405'],
        ]);
        $audioPrompt = Exercise::factory()->create([
            'type' => 'spelling_quiz',
            'content' => ['audio_url' => '/audio/reservation.mp3', 'correct_answer' => 'reservation'],
        ]);
        $remotePrompt = Exercise::factory()->create([
            'type' => 'spelling_quiz',
            'content' => ['audio_url' => 'https://unapproved.example/prompt.mp3', 'correct_answer' => 'safe prompt'],
        ]);
        $migration = require database_path('migrations/2026_07_16_000002_migrate_spelling_prompt_contract.php');

        $migration->up();

        $this->assertSame([
            'correct_answer' => 'Room 405',
            'prompt_text' => 'Room 405',
        ], $textPrompt->fresh()->content);
        $this->assertSame([
            'audio_url' => '/audio/reservation.mp3',
            'correct_answer' => 'reservation',
            'prompt_text' => 'reservation',
        ], $audioPrompt->fresh()->content);
        $this->assertSame([
            'correct_answer' => 'safe prompt',
            'prompt_text' => 'safe prompt',
        ], $remotePrompt->fresh()->content);

        $migration->down();

        $this->assertSame([
            'correct_answer' => 'Room 405',
            'audio_url' => 'Room 405',
        ], $textPrompt->fresh()->content);
        $this->assertSame([
            'audio_url' => '/audio/reservation.mp3',
            'correct_answer' => 'reservation',
        ], $audioPrompt->fresh()->content);
    }
}
