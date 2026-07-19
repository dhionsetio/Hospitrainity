<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\User;
use App\Models\Vocabulary;
use App\Models\VocabularyItem;
use App\Services\PublicMediaManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class VocabularyMediaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }

    public function test_foreign_item_id_is_rejected_without_mutation(): void
    {
        $first = Vocabulary::factory()->create(['category' => 'First']);
        $ownItem = VocabularyItem::factory()->create(['vocabulary_id' => $first->id, 'term' => 'Own']);
        $foreignItem = VocabularyItem::factory()->create(['term' => 'Foreign']);

        $this->actingAs($this->admin())->put(route('superadmin.vocabularies.update', $first), [
            'lesson_id' => $first->lesson_id,
            'category' => 'Tampered',
            'items' => [['id' => $foreignItem->id, 'term' => 'Changed', 'details' => null]],
        ])->assertSessionHasErrors('items.0.id');

        $this->assertSame('First', $first->fresh()->category);
        $this->assertSame('Own', $ownItem->fresh()->term);
        $this->assertSame('Foreign', $foreignItem->fresh()->term);
    }

    public function test_client_authoritative_existing_media_path_is_rejected(): void
    {
        $vocabulary = Vocabulary::factory()->create();
        $item = VocabularyItem::factory()->create([
            'vocabulary_id' => $vocabulary->id,
            'media_url' => '/storage/curriculum/vocabulary/original.mp3',
        ]);

        $this->actingAs($this->admin())->put(route('superadmin.vocabularies.update', $vocabulary), [
            'lesson_id' => $vocabulary->lesson_id,
            'category' => $vocabulary->category,
            'items' => [[
                'id' => $item->id,
                'term' => $item->term,
                'details' => $item->details,
                'existing_media_url' => '/storage/attacker-selected.mp3',
            ]],
        ])->assertSessionHasErrors('items.0');

        $this->assertSame('/storage/curriculum/vocabulary/original.mp3', $item->fresh()->media_url);
    }

    public function test_replacement_is_committed_before_old_media_is_deleted(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('curriculum/vocabulary/original.mp3', 'old');
        $vocabulary = Vocabulary::factory()->create();
        $item = VocabularyItem::factory()->create([
            'vocabulary_id' => $vocabulary->id,
            'media_url' => '/storage/curriculum/vocabulary/original.mp3',
        ]);

        $this->actingAs($this->admin())->put(route('superadmin.vocabularies.update', $vocabulary), [
            'lesson_id' => $vocabulary->lesson_id,
            'category' => $vocabulary->category,
            'items' => [[
                'id' => $item->id,
                'term' => $item->term,
                'details' => $item->details,
                'media' => UploadedFile::fake()->create('replacement.mp3', 10, 'audio/mpeg'),
            ]],
        ])->assertRedirect(route('superadmin.vocabularies.index'));

        Storage::disk('public')->assertMissing('curriculum/vocabulary/original.mp3');
        $newPath = PublicMediaManager::pathFromUrl($item->fresh()->media_url);
        $this->assertNotNull($newPath);
        Storage::disk('public')->assertExists($newPath);
        $this->assertDatabaseCount('pending_media_deletions', 0);
    }

    public function test_database_failure_removes_staged_upload_and_rolls_back_rows(): void
    {
        Storage::fake('public');
        $lesson = Lesson::factory()->create();
        VocabularyItem::creating(static function (): never {
            throw new RuntimeException('Injected vocabulary item failure.');
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->admin())->post(route('superadmin.vocabularies.store'), [
                'lesson_id' => $lesson->id,
                'category' => 'Rollback fixture',
                'items' => [[
                    'term' => 'Rollback',
                    'details' => 'Must not persist',
                    'media' => UploadedFile::fake()->create('rollback.mp3', 10, 'audio/mpeg'),
                ]],
            ]);
            $this->fail('The injected database failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected vocabulary item failure.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('vocabularies', ['category' => 'Rollback fixture']);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('pending_media_deletions', 0);
    }
}
