<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Material;
use App\Models\MaterialItem;
use App\Models\Module;
use App\Models\User;
use App\Models\Vocabulary;
use App\Models\VocabularyItem;
use App\Services\PublicMediaManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MediaLifecycleCascadeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }

    public function test_module_delete_cleans_descendant_media_bypassed_by_database_cascades(): void
    {
        Storage::fake('public');
        foreach ([
            'curriculum/vocabulary/word.mp3',
            'curriculum/materials/images/image.png',
            'curriculum/materials/audio/narration.mp3',
        ] as $path) {
            Storage::disk('public')->put($path, 'fixture');
        }

        $module = Module::factory()->create();
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);
        $vocabulary = Vocabulary::factory()->create(['lesson_id' => $lesson->id]);
        VocabularyItem::factory()->create([
            'vocabulary_id' => $vocabulary->id,
            'media_url' => '/storage/curriculum/vocabulary/word.mp3',
        ]);
        $material = Material::factory()->create(['lesson_id' => $lesson->id, 'type' => 'Gambar']);
        MaterialItem::factory()->create([
            'material_id' => $material->id,
            'url' => '/storage/curriculum/materials/images/image.png',
            'audio_url' => '/storage/curriculum/materials/audio/narration.mp3',
        ]);

        $this->actingAs($this->admin())->delete(route('superadmin.modules.destroy', $module))
            ->assertRedirect(route('superadmin.modules.index'));

        Storage::disk('public')->assertMissing([
            'curriculum/vocabulary/word.mp3',
            'curriculum/materials/images/image.png',
            'curriculum/materials/audio/narration.mp3',
        ]);
        $this->assertDatabaseMissing('modules', ['id' => $module->id]);
        $this->assertDatabaseCount('pending_media_deletions', 0);
    }

    public function test_file_write_failure_leaves_database_unchanged(): void
    {
        $manager = Mockery::mock(PublicMediaManager::class);
        $manager->shouldReceive('retire')->once()->with(null);
        $manager->shouldReceive('stage')->once()->andThrow(new RuntimeException('Injected storage write failure.'));
        $manager->shouldReceive('rollbackStaged')->once();
        $this->app->instance(PublicMediaManager::class, $manager);
        $lesson = Lesson::factory()->create();

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->admin())->post(route('superadmin.materials.store'), [
                'lesson_id' => $lesson->id,
                'type' => 'Audio',
                'items' => [[
                    'title' => 'Failure fixture',
                    'description' => 'Must not persist',
                    'file' => UploadedFile::fake()->create('audio.mp3', 10, 'audio/mpeg'),
                ]],
            ]);
            $this->fail('The injected storage failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected storage write failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('materials', 0);
        $this->assertDatabaseCount('material_items', 0);
        $this->assertDatabaseCount('pending_media_deletions', 0);
    }
}
