<?php

namespace Tests\Feature;

use App\Models\ChapterProgress;
use App\Models\User;
use App\Services\Curriculum\CanonicalCurriculumImporter;
use App\Services\Curriculum\CanonicalPackageReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InteractiveBookShellTest extends TestCase
{
    use RefreshDatabase;

    private function importActive(): void
    {
        app(CanonicalCurriculumImporter::class)->import(app(CanonicalPackageReader::class)->read());
    }

    public function test_chapter_progress_can_be_persisted_and_retrieved(): void
    {
        $user = User::factory()->create();

        $progress = ChapterProgress::create([
            'user_id' => $user->id,
            'learning_scope_key' => 'personal',
            'chapter_code' => 'HSP-C01',
            'last_section_code' => 'HSP-C01-S01-SEC02',
        ]);

        $this->assertDatabaseHas('chapter_progress', [
            'user_id' => $user->id,
            'learning_scope_key' => 'personal',
            'chapter_code' => 'HSP-C01',
            'last_section_code' => 'HSP-C01-S01-SEC02',
        ]);
    }

    public function test_user_can_access_chapter_view(): void
    {
        $this->importActive();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/curriculum/HSP-C01');
        $response->assertOk()
            ->assertSee('Module 1')
            ->assertSee('What you will practise');
    }
}
