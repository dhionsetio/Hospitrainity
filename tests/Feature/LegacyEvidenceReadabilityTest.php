<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\Module;
use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyEvidenceReadabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_admin_can_read_nested_legacy_evidence_without_edit_controls(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);
        $module = Module::factory()->create([
            'slug' => 'retained-front-office',
            'description' => 'Retained module description for audit review.',
        ]);
        $lesson = Lesson::factory()->for($module)->create(['slug' => 'retained-check-in']);
        $vocabulary = Vocabulary::factory()->for($lesson)->create(['category' => 'Arrival language']);
        $vocabulary->items()->create([
            'term' => 'reservation',
            'details' => 'A booking retained from the legacy curriculum.',
            'media_url' => '/storage/curriculum/vocabulary/reservation.mp3',
            'order' => 1,
        ]);
        $material = Material::factory()->for($lesson)->create(['type' => 'Reading']);
        $material->items()->create([
            'title' => 'Legacy check-in dialogue',
            'description' => 'A retained dialogue transcript.',
            'url' => '/storage/curriculum/materials/dialogue.pdf',
            'audio_url' => '/storage/curriculum/materials/dialogue.mp3',
            'order' => 1,
        ]);
        Exercise::factory()->for($lesson)->create([
            'title' => 'Legacy arrival check',
            'content' => [
                'question_text' => 'What should the receptionist confirm?',
                'options' => ['Reservation name', 'Room colour'],
                'correct_answer' => 'Reservation name',
            ],
        ]);

        $this->actingAs($admin)->get(route('admin.modules.index'))
            ->assertOk()
            ->assertSee('View stored details')
            ->assertSee('Retained module description for audit review.')
            ->assertDontSee('Create module');
        $this->get(route('admin.lessons.index'))
            ->assertOk()
            ->assertSee('retained-check-in');
        $this->get(route('admin.vocabularies.index'))
            ->assertOk()
            ->assertSee('reservation')
            ->assertSee('A booking retained from the legacy curriculum.');
        $this->get(route('admin.materials.index'))
            ->assertOk()
            ->assertSee('Legacy check-in dialogue')
            ->assertSee('/storage/curriculum/materials/dialogue.mp3');
        $this->get(route('admin.exercises.index'))
            ->assertOk()
            ->assertSee('What should the receptionist confirm?')
            ->assertSee('Reservation name');
    }
}
