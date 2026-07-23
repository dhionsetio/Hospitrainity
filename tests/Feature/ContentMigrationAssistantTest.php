<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Curriculum\CurriculumDraftWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentMigrationAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_access_draft_exercise_authoring(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $draft = app(CurriculumDraftWorkspace::class)->create($superadmin, [
            'source' => 'blank',
            'content_version' => '0.5.0-draft',
            'title' => 'Migration Test Draft',
        ]);

        $response = $this->actingAs($superadmin)->get(route('superadmin.curriculum-drafts.exercises.index', $draft));
        $response->assertOk()
            ->assertSee('Quiz / Question Set');
    }
}
