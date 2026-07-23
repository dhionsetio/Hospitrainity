<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Curriculum\CanonicalCurriculumImporter;
use App\Services\Curriculum\CanonicalPackageReader;
use App\Services\GreetingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTwoFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(CanonicalCurriculumImporter::class)->import(app(CanonicalPackageReader::class)->read());
    }

    public function test_greeting_service_formats_questions_and_statements_correctly(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Dhion',
            'last_name' => 'Setio',
            'timezone' => 'Asia/Jakarta',
        ]);

        $service = new GreetingService;
        $greeting = $service->greeting($user);

        if (str_contains($greeting, '?')) {
            $this->assertStringEndsWith('Dhion Setio?', $greeting);
        } else {
            $this->assertStringEndsWith('Dhion Setio!', $greeting);
        }
    }

    public function test_activity_view_renders_compact_summary_header_and_tts_control(): void
    {
        $learner = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($learner)->get(route('curriculum.activities.show', 'HSP-C01-ACT-BASELINE'));

        $response->assertOk()
            ->assertSee('id="activity-content"', escape: false)
            ->assertSee(__('engagement.listen_lesson'));
    }

    public function test_activity_view_renders_open_text_affordance_for_practice_activity(): void
    {
        $learner = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($learner)->get(route('curriculum.activities.show', 'HSP-C02-ACT-PRACTICE'));

        $response->assertOk()
            ->assertSee('Click to Type', escape: false);
    }
}
