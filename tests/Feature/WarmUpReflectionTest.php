<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CurriculumEntity;
use App\Models\PendingMediaDeletion;
use App\Models\User;
use App\Models\WarmUpReflection;
use App\Services\Reflections\WarmUpReflectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\InstallsCanonicalCurriculumFixture;
use Tests\TestCase;

class WarmUpReflectionTest extends TestCase
{
    use InstallsCanonicalCurriculumFixture;
    use RefreshDatabase;

    public function test_learner_can_save_and_submit_warm_up_reflection(): void
    {
        $this->installCanonicalCurriculumFixture();
        $sectionEntity = CurriculumEntity::query()
            ->where('entity_type', 'lesson-section')
            ->where('position', 1)
            ->firstOrFail();
        $sectionCode = $sectionEntity->code;
        $learner = User::factory()->create([
            'role' => UserRole::Learner,
            'email_verified_at' => now(),
        ]);
        $responseKey = (string) Str::uuid();
        $body = 'I would greet the guest warmly and offer water.';

        $response = $this->actingAs($learner)
            ->post(route('curriculum.reflections.store', [$sectionCode, 0]), [
                'response_key' => $responseKey,
                'intent' => 'save',
                'section_code' => $sectionCode,
                'prompt_index' => 0,
                'body' => $body,
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $response->assertSessionHas('status');

        $reflection = WarmUpReflection::query()
            ->where('user_id', $learner->id)
            ->where('section_code', $sectionCode)
            ->where('prompt_index', 0)
            ->firstOrFail();

        $this->assertSame('draft', $reflection->state);

        $rawBody = DB::table('warm_up_reflections')
            ->where('id', $reflection->id)
            ->value('body');
        $this->assertNotEquals($body, $rawBody);
        $this->assertStringNotContainsString($body, (string) $rawBody);

        // Submit reflection
        $submitResponse = $this->actingAs($learner)
            ->post(route('curriculum.reflections.store', [$sectionCode, 0]), [
                'response_key' => $responseKey,
                'intent' => 'submit',
                'section_code' => $sectionCode,
                'prompt_index' => 0,
                'body' => $body,
            ]);

        $submitResponse->assertRedirect();
        $reflection->refresh();
        $this->assertSame('submitted', $reflection->state);
        $this->assertNotNull($reflection->submitted_at);
    }

    public function test_learner_can_attach_media_file_to_reflection(): void
    {
        $this->installCanonicalCurriculumFixture();
        Storage::fake('learner_media_private');

        $sectionEntity = CurriculumEntity::query()
            ->where('entity_type', 'lesson-section')
            ->where('position', 1)
            ->firstOrFail();
        $sectionCode = $sectionEntity->code;

        $learner = User::factory()->create([
            'role' => UserRole::Learner,
            'email_verified_at' => now(),
        ]);

        $responseKey = (string) Str::uuid();

        $tmpWav = tempnam(sys_get_temp_dir(), 'test_wav');
        file_put_contents($tmpWav, "RIFF\x24\x00\x00\x00WAVEfmt \x10\x00\x00\x00\x01\x00\x01\x00\x44\xAC\x00\x00");

        $fakeFile = new UploadedFile($tmpWav, 'voice.wav', 'audio/wav', null, true);

        $response = $this->actingAs($learner)
            ->post(route('curriculum.reflections.store', [$sectionCode, 0]), [
                'response_key' => $responseKey,
                'intent' => 'submit',
                'section_code' => $sectionCode,
                'prompt_index' => 0,
                'recording_kind' => 'voice_recording',
                'attachments' => [$fakeFile],
            ]);

        @unlink($tmpWav);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $reflection = WarmUpReflection::query()
            ->where('user_id', $learner->id)
            ->where('section_code', $sectionCode)
            ->where('prompt_index', 0)
            ->with('attachments')
            ->firstOrFail();

        $this->assertCount(1, $reflection->attachments);
        $attachment = $reflection->attachments->first();
        $this->assertSame('voice_recording', $attachment->kind);
        $this->assertSame('voice.wav', $attachment->original_name);
        $this->assertContains($attachment->scan_status, ['clean', 'unavailable']);

        // Test serve route authorization
        $serveResponse = $this->actingAs($learner)
            ->get(route('curriculum.reflections.attachments.show', [$reflection->id, $attachment->id]));

        $serveResponse->assertOk();
        $serveResponse->assertHeader('X-Content-Type-Options', 'nosniff');
        $serveResponse->assertHeader('Cache-Control', 'no-store, private');

        // Test unauthorized access by another learner
        $otherLearner = User::factory()->create(['role' => UserRole::Learner]);
        $this->actingAs($otherLearner)
            ->get(route('curriculum.reflections.attachments.show', [$reflection->id, $attachment->id]))
            ->assertForbidden();

        // Test DataExportBuilder integration
        $export = app(\App\Services\DataExportBuilder::class)->build($learner);
        $temporary = tempnam(sys_get_temp_dir(), 'hospitrainity-reflection-export-');
        $this->assertNotFalse($temporary);
        file_put_contents($temporary, $export['bytes']);
        $archive = new \ZipArchive;
        $this->assertTrue($archive->open($temporary));
        $savedReflections = $archive->getFromName('warm-up-reflections.csv');
        $this->assertIsString($savedReflections);
        $this->assertStringContainsString($sectionCode, $savedReflections);
        $mediaFile = $archive->getFromName('media/reflections/'.$attachment->id.'-'.$attachment->original_name);
        $this->assertIsString($mediaFile);
        $archive->close();
        unlink($temporary);
    }

    public function test_one_answer_per_prompt_upserts_same_row(): void
    {
        $this->installCanonicalCurriculumFixture();
        $sectionEntity = CurriculumEntity::query()
            ->where('entity_type', 'lesson-section')
            ->where('position', 1)
            ->firstOrFail();
        $sectionCode = $sectionEntity->code;
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        $this->actingAs($learner)
            ->post(route('curriculum.reflections.store', [$sectionCode, 0]), [
                'response_key' => (string) Str::uuid(),
                'intent' => 'save',
                'section_code' => $sectionCode,
                'prompt_index' => 0,
                'body' => 'Initial draft text.',
            ])->assertRedirect();

        $this->assertSame(1, WarmUpReflection::query()->where('user_id', $learner->id)->count());

        $this->post(route('curriculum.reflections.store', [$sectionCode, 0]), [
            'response_key' => (string) Str::uuid(),
            'intent' => 'save',
            'section_code' => $sectionCode,
            'prompt_index' => 0,
            'body' => 'Updated draft text.',
        ])->assertRedirect();

        $this->assertSame(1, WarmUpReflection::query()->where('user_id', $learner->id)->count());
        $this->assertSame('Updated draft text.', WarmUpReflection::query()->where('user_id', $learner->id)->first()->body);

        // Different prompt index creates separate row
        $this->post(route('curriculum.reflections.store', [$sectionCode, 1]), [
            'response_key' => (string) Str::uuid(),
            'intent' => 'save',
            'section_code' => $sectionCode,
            'prompt_index' => 1,
            'body' => 'Second prompt answer.',
        ])->assertRedirect();

        $this->assertSame(2, WarmUpReflection::query()->where('user_id', $learner->id)->count());
    }

    public function test_existing_for_section_respects_scope_isolation(): void
    {
        $this->installCanonicalCurriculumFixture();
        $sectionEntity = CurriculumEntity::query()
            ->where('entity_type', 'lesson-section')
            ->where('position', 1)
            ->firstOrFail();
        $sectionCode = $sectionEntity->code;
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        // Create personal reflection
        $this->actingAs($learner)
            ->post(route('curriculum.reflections.store', [$sectionCode, 0]), [
                'response_key' => (string) Str::uuid(),
                'intent' => 'save',
                'section_code' => $sectionCode,
                'prompt_index' => 0,
                'body' => 'Personal scope reflection.',
            ])->assertRedirect();

        $service = app(WarmUpReflectionService::class);
        $personalSet = $service->existingForSection($learner, $sectionCode);
        $this->assertCount(1, $personalSet);
        $this->assertSame('Personal scope reflection.', $personalSet->get(0)->body);
    }

    public function test_shared_blob_deletion_preserves_remaining_reference(): void
    {
        $this->installCanonicalCurriculumFixture();
        Storage::fake('learner_media_private');

        $learnerA = User::factory()->create(['role' => UserRole::Learner]);
        $learnerB = User::factory()->create(['role' => UserRole::Learner]);

        $sectionEntity = CurriculumEntity::query()
            ->where('entity_type', 'lesson-section')
            ->where('position', 1)
            ->firstOrFail();
        $sectionCode = $sectionEntity->code;

        $tmpWav = tempnam(sys_get_temp_dir(), 'shared_wav');
        file_put_contents($tmpWav, "RIFF\x24\x00\x00\x00WAVEfmt \x10\x00\x00\x00\x01\x00\x01\x00\x44\xAC\x00\x00");

        // Learner A saves media
        $fileA = new UploadedFile($tmpWav, 'voice.wav', 'audio/wav', null, true);
        $this->actingAs($learnerA)
            ->post(route('curriculum.reflections.store', [$sectionCode, 0]), [
                'response_key' => (string) Str::uuid(),
                'intent' => 'save',
                'section_code' => $sectionCode,
                'prompt_index' => 0,
                'attachments' => [$fileA],
            ]);

        // Learner B saves identical media
        $fileB = new UploadedFile($tmpWav, 'voice.wav', 'audio/wav', null, true);
        $this->actingAs($learnerB)
            ->post(route('curriculum.reflections.store', [$sectionCode, 0]), [
                'response_key' => (string) Str::uuid(),
                'intent' => 'save',
                'section_code' => $sectionCode,
                'prompt_index' => 0,
                'attachments' => [$fileB],
            ]);

        @unlink($tmpWav);

        $reflectionA = WarmUpReflection::query()->where('user_id', $learnerA->id)->with('attachments')->firstOrFail();
        $reflectionB = WarmUpReflection::query()->where('user_id', $learnerB->id)->with('attachments')->firstOrFail();

        $pathA = $reflectionA->attachments->first()->storage_path;
        $pathB = $reflectionB->attachments->first()->storage_path;
        $this->assertSame($pathA, $pathB);

        // Delete reflection A
        $reflectionA->delete();

        // Blob file must still exist on disk because reflection B references it!
        Storage::disk('learner_media_private')->assertExists($pathB);

        // PendingMediaDeletion queue must NOT contain the blob path yet!
        $this->assertDatabaseMissing('pending_media_deletions', [
            'disk' => 'learner_media_private',
            'path' => $pathB,
        ]);

        // Now delete reflection B
        $reflectionB->delete();

        // Now PendingMediaDeletion queue MUST contain the blob path!
        $this->assertDatabaseHas('pending_media_deletions', [
            'disk' => 'learner_media_private',
            'path' => $pathB,
        ]);
    }
}
