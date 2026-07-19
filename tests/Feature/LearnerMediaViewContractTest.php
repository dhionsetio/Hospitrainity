<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Material;
use App\Models\MaterialItem;
use App\Models\Module;
use App\Models\User;
use App\Models\Vocabulary;
use App\Models\VocabularyItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearnerMediaViewContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_material_renderer_registry_matches_the_server_enum_and_has_error_states(): void
    {
        $source = file_get_contents(resource_path('views/material.blade.php'));
        $this->assertIsString($source);

        foreach (Material::TYPES as $type) {
            $this->assertStringContainsString("            {$type}(item) {", $source);
        }

        $this->assertStringNotContainsString('Gambar dengan Audio', $source);
        $this->assertStringNotContainsString("source.type = 'audio/mpeg'", $source);
        $this->assertStringContainsString('audio.src = item.url', $source);
        $this->assertStringContainsString("audio.addEventListener('error'", $source);
        $this->assertStringContainsString("image.addEventListener('error'", $source);
        $this->assertStringContainsString('role="status" aria-live="polite"', $source);
    }

    public function test_vocabulary_and_material_pages_render_accessible_media_status_and_controls(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $module = Module::factory()->create();
        $lesson = Lesson::factory()->for($module)->create();
        $vocabulary = Vocabulary::factory()->for($lesson)->create();
        VocabularyItem::factory()->for($vocabulary)->create(['term' => 'reservation', 'media_url' => null]);
        $material = Material::factory()->for($lesson)->create(['type' => 'Teks']);
        MaterialItem::factory()->for($material)->create(['title' => 'Overview', 'audio_url' => null]);

        $practice = $this->actingAs($user)->get(route('lessons.practice', [$lesson, $vocabulary]));
        $practice->assertOk()
            ->assertSee('id="media-status" role="status" aria-live="polite"', false)
            ->assertSee('termContainer.appendChild(speakButton)', false)
            ->assertDontSee('// speakButton.onclick', false);

        $materialPage = $this->actingAs($user)->get(route('lessons.material.show', [$lesson, $material]));
        $materialPage->assertOk()
            ->assertSee('id="media-status" role="status" aria-live="polite"', false)
            ->assertSee('const MATERIAL_RENDERERS', false)
            ->assertSee('Teks(item)', false);
    }
}
