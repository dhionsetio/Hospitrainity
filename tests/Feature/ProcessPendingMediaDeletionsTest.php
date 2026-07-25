<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WarmUpReflection;
use App\Models\WarmUpReflectionAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\InstallsCanonicalCurriculumFixture;
use Tests\TestCase;

class ProcessPendingMediaDeletionsTest extends TestCase
{
    use InstallsCanonicalCurriculumFixture;
    use RefreshDatabase;

    public function test_command_deletes_orphaned_reflection_attachments_and_files(): void
    {
        Storage::fake('learner_media_private');

        $user = User::factory()->create();
        $package = $this->installCanonicalCurriculumFixture();

        $reflection = WarmUpReflection::query()->create([
            'user_id' => $user->id,
            'curriculum_package_id' => $package->id,
            'response_key' => (string) \Illuminate\Support\Str::uuid(),
            'learning_scope_key' => 'personal',
            'chapter_code' => 'HSP-C01',
            'section_code' => 'HSP-C01-SEC-01',
            'section_source_sha256' => str_repeat('a', 64),
            'prompt_index' => 0,
            'prompt_fingerprint' => str_repeat('b', 64),
            'state' => 'draft',
        ]);

        $filePath = 'reflections/blobs/test/orphan.wav';
        Storage::disk('learner_media_private')->put($filePath, 'RIFF...WAVE');

        $attachment = WarmUpReflectionAttachment::query()->create([
            'warm_up_reflection_id' => $reflection->id,
            'kind' => 'voice_recording',
            'disk' => 'learner_media_private',
            'storage_path' => $filePath,
            'original_name' => 'orphan.wav',
            'detected_mime' => 'audio/wav',
            'byte_size' => 10,
            'sha256' => str_repeat('c', 64),
            'scan_status' => 'clean',
        ]);

        // Delete parent reflection to make attachment orphaned
        $reflection->delete();

        $this->artisan('learning:process-pending-media-deletions')
            ->assertExitCode(0);

        Storage::disk('learner_media_private')->assertMissing($filePath);
        $this->assertDatabaseMissing('warm_up_reflection_attachments', [
            'id' => $attachment->id,
        ]);
    }
}
