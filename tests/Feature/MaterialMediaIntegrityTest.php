<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Material;
use App\Models\MaterialItem;
use App\Models\PendingMediaDeletion;
use App\Models\User;
use App\Services\PublicMediaManager;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MaterialMediaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }

    public function test_material_update_derives_immutable_type_when_field_is_omitted(): void
    {
        $material = Material::factory()->create(['type' => 'Teks']);
        $item = MaterialItem::factory()->create(['material_id' => $material->id, 'description' => 'Before']);

        $this->actingAs($this->admin())->put(route('superadmin.materials.update', $material), [
            'lesson_id' => $material->lesson_id,
            'items' => [['id' => $item->id, 'title' => 'Updated', 'description' => 'After']],
        ])->assertRedirect(route('superadmin.materials.index'));

        $this->assertSame('Teks', $material->fresh()->type);
        $this->assertSame('After', $item->fresh()->description);
    }

    public function test_foreign_material_item_id_is_rejected_atomically(): void
    {
        $material = Material::factory()->create(['type' => 'Teks']);
        $own = MaterialItem::factory()->create(['material_id' => $material->id, 'description' => 'Own']);
        $foreign = MaterialItem::factory()->create(['description' => 'Foreign']);

        $this->actingAs($this->admin())->put(route('superadmin.materials.update', $material), [
            'lesson_id' => $material->lesson_id,
            'type' => 'Teks',
            'items' => [['id' => $foreign->id, 'description' => 'Changed']],
        ])->assertSessionHasErrors('items.0.id');

        $this->assertSame('Own', $own->fresh()->description);
        $this->assertSame('Foreign', $foreign->fresh()->description);
        $this->assertDatabaseHas('material_items', ['id' => $own->id]);
    }

    public function test_video_material_accepts_only_canonical_https_youtube_urls(): void
    {
        $lessonId = Lesson::factory()->create()->id;
        $base = [
            'lesson_id' => $lessonId,
            'type' => 'Video',
            'items' => [['title' => 'Video', 'description' => 'Watch this video']],
        ];

        $invalid = $base;
        $invalid['items'][0]['url'] = 'https://example.com/watch?v=dQw4w9WgXcQ';
        $this->actingAs($this->admin())->post(route('superadmin.materials.store'), $invalid)
            ->assertSessionHasErrors('items.0.url');

        $valid = $base;
        $valid['items'][0]['url'] = 'https://youtu.be/dQw4w9WgXcQ';
        $this->actingAs($this->admin())->post(route('superadmin.materials.store'), $valid)
            ->assertRedirect(route('superadmin.materials.index'));

        $this->assertDatabaseHas('material_items', [
            'url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ',
        ]);
    }

    public function test_image_and_audio_materials_require_primary_uploads(): void
    {
        $lessonId = Lesson::factory()->create()->id;

        foreach (['Gambar', 'Audio'] as $type) {
            $this->actingAs($this->admin())->post(route('superadmin.materials.store'), [
                'lesson_id' => $lessonId,
                'type' => $type,
                'items' => [['title' => $type, 'description' => 'Missing required file']],
            ])->assertSessionHasErrors('items.0.file');
        }

        $this->assertDatabaseCount('materials', 0);
    }

    public function test_image_and_optional_audio_are_stored_in_consistent_public_directories(): void
    {
        Storage::fake('public');
        $lessonId = Lesson::factory()->create()->id;
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        $this->assertIsString($png);

        $this->actingAs($this->admin())->post(route('superadmin.materials.store'), [
            'lesson_id' => $lessonId,
            'type' => 'Gambar',
            'items' => [[
                'title' => 'Image fixture',
                'description' => 'Stored media fixture',
                'file' => UploadedFile::fake()->createWithContent('image.png', $png),
                'audio_file' => UploadedFile::fake()->create('narration.mp3', 10, 'audio/mpeg'),
            ]],
        ])->assertRedirect(route('superadmin.materials.index'));

        $item = MaterialItem::query()->firstOrFail();
        $imagePath = PublicMediaManager::pathFromUrl($item->url);
        $audioPath = PublicMediaManager::pathFromUrl($item->audio_url);
        $this->assertStringStartsWith('curriculum/materials/images/', $imagePath);
        $this->assertStringStartsWith('curriculum/materials/audio/', $audioPath);
        Storage::disk('public')->assertExists([$imagePath, $audioPath]);
    }

    public function test_all_material_types_complete_the_admin_crud_contract(): void
    {
        Storage::fake('public');
        $lessonId = Lesson::factory()->create()->id;
        $admin = $this->admin();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        $this->assertIsString($png);

        foreach (Material::TYPES as $type) {
            $storeItem = ['title' => "{$type} title", 'description' => "{$type} description"];
            if ($type === 'Audio') {
                $storeItem['file'] = UploadedFile::fake()->create('sample.mp3', 10, 'audio/mpeg');
            } elseif ($type === 'Gambar') {
                $storeItem['file'] = UploadedFile::fake()->createWithContent('sample.png', $png);
            } elseif ($type === 'Video') {
                $storeItem['url'] = 'https://youtu.be/dQw4w9WgXcQ';
            }

            $this->actingAs($admin)->post(route('superadmin.materials.store'), [
                'lesson_id' => $lessonId,
                'type' => $type,
                'items' => [$storeItem],
            ])->assertRedirect(route('superadmin.materials.index'));

            $material = Material::query()->latest('id')->with('items')->firstOrFail();
            $item = $material->items->sole();
            $localPath = PublicMediaManager::pathFromUrl($item->url);
            if ($localPath !== null) {
                Storage::disk('public')->assertExists($localPath);
            }

            $updateItem = [
                'id' => $item->id,
                'title' => "Updated {$type}",
                'description' => "Updated {$type} description",
            ];
            if ($type === 'Video') {
                $updateItem['url'] = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
            }

            $this->actingAs($admin)->put(route('superadmin.materials.update', $material), [
                'lesson_id' => $lessonId,
                'items' => [$updateItem],
            ])->assertRedirect(route('superadmin.materials.index'));

            $this->assertSame($type, $material->fresh()->type);
            $this->assertSame("Updated {$type} description", $item->fresh()->description);

            $this->actingAs($admin)
                ->delete(route('superadmin.materials.destroy', $material))
                ->assertRedirect(route('superadmin.materials.index'));
            $this->assertDatabaseMissing('materials', ['id' => $material->id]);
            if ($localPath !== null) {
                Storage::disk('public')->assertMissing($localPath);
            }
        }

        $this->assertDatabaseCount('materials', 0);
        $this->assertDatabaseCount('pending_media_deletions', 0);
    }

    public function test_failed_after_commit_delete_remains_in_durable_cleanup_queue(): void
    {
        $pending = PendingMediaDeletion::create([
            'disk' => 'public',
            'path' => 'curriculum/materials/audio/pending.mp3',
        ]);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->once()->with($pending->path)->andReturnTrue();
        $disk->shouldReceive('delete')->once()->with($pending->path)->andThrow(new RuntimeException('Injected delete failure.'));
        Storage::shouldReceive('disk')->once()->with('public')->andReturn($disk);

        $result = app(PublicMediaManager::class)->processPending();

        $this->assertSame(['processed' => 0, 'remaining' => 1], $result);
        $this->assertSame(1, $pending->fresh()->attempts);
        $this->assertSame('Injected delete failure.', $pending->fresh()->last_error);
    }

    public function test_public_media_url_parser_rejects_non_storage_and_traversal_paths(): void
    {
        $this->assertSame('curriculum/materials/audio/test.mp3', PublicMediaManager::pathFromUrl('/storage/curriculum/materials/audio/test.mp3'));
        $this->assertNull(PublicMediaManager::pathFromUrl('https://example.com/video.mp4'));
        $this->assertNull(PublicMediaManager::pathFromUrl('https://example.com/storage/curriculum/materials/audio/test.mp3'));
        $this->assertNull(PublicMediaManager::pathFromUrl('/storage/%2e%2e/.env'));
        $this->assertNull(PublicMediaManager::pathFromUrl('/storage/safe%5c..%5c.env'));
    }
}
