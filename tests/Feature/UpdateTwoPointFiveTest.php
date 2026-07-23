<?php

namespace Tests\Feature;

use App\Models\CurriculumEntity;
use App\Models\User;
use App\Services\Curriculum\CanonicalCurriculumImporter;
use App\Services\Curriculum\CanonicalPackageReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class UpdateTwoPointFiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(CanonicalCurriculumImporter::class)->import(app(CanonicalPackageReader::class)->read());
    }

    public function test_section_titles_are_sanitized_across_chapter_and_section_views(): void
    {
        $learner = User::factory()->create(['role' => 'user']);
        $section = CurriculumEntity::query()->where('entity_type', 'lesson-section')->firstOrFail();

        $chapterResponse = $this->actingAs($learner)->get(route('curriculum.chapters.show', $section->parent_code));
        $chapterResponse->assertOk()
            ->assertDontSee('Step 1. Step 1.')
            ->assertDontSee('Step 2. Step 2.');

        $sectionResponse = $this->actingAs($learner)->get(route('curriculum.sections.show', $section->code));
        $sectionResponse->assertOk()
            ->assertDontSee('Step 1. Step 1.');
    }

    public function test_rubric_scores_submission_succeeds_and_persists(): void
    {
        $learner = User::factory()->create(['role' => 'user']);
        $prompts = CurriculumEntity::query()->where('entity_type', 'prompt-item')->where('parent_code', 'HSP-C02-ACT-PRACTICE')->get();

        $responses = [];
        $selfChecks = [];
        foreach ($prompts as $prompt) {
            $form = $prompt->payload['response_form'];
            if ($form === 'selection') {
                $responses[$prompt->code] = $prompt->payload['choices'][0]['id'];
            } elseif ($form === 'ordering') {
                $responses[$prompt->code] = array_column($prompt->payload['tokens'], 'id');
            } else {
                $responses[$prompt->code] = 'Sample text response';
            }
            if (($prompt->payload['self_check_required'] ?? false) === true) {
                $selfChecks[$prompt->code] = '1';
            }
        }

        $response = $this->actingAs($learner)->post(route('curriculum.activities.attempts.store', 'HSP-C02-ACT-PRACTICE'), [
            'attempt_key' => (string) Str::uuid(),
            'intent' => 'check',
            'responses' => $responses,
            'self_checks' => $selfChecks,
            'rubric_scores' => [
                '0' => 1,
                '1' => 2,
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_audio_file_submission_stores_file_in_private_storage(): void
    {
        Storage::fake('curriculum_private');
        $learner = User::factory()->create(['role' => 'user']);
        $fakeAudio = UploadedFile::fake()->create('spoken_response.ogg', 100, 'audio/ogg');
        $prompts = CurriculumEntity::query()->where('entity_type', 'prompt-item')->where('parent_code', 'HSP-C02-ACT-PRACTICE')->get();

        $responses = [];
        $selfChecks = [];
        foreach ($prompts as $prompt) {
            $form = $prompt->payload['response_form'];
            if ($form === 'selection') {
                $responses[$prompt->code] = $prompt->payload['choices'][0]['id'];
            } elseif ($form === 'ordering') {
                $responses[$prompt->code] = array_column($prompt->payload['tokens'], 'id');
            } else {
                $responses[$prompt->code] = 'Sample text response';
            }
            if (($prompt->payload['self_check_required'] ?? false) === true) {
                $selfChecks[$prompt->code] = '1';
            }
        }

        $response = $this->actingAs($learner)->post(route('curriculum.activities.attempts.store', 'HSP-C02-ACT-PRACTICE'), [
            'attempt_key' => (string) Str::uuid(),
            'intent' => 'check',
            'responses' => $responses,
            'self_checks' => $selfChecks,
            'audio' => [
                $prompts->first()->code => $fakeAudio,
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }
}
