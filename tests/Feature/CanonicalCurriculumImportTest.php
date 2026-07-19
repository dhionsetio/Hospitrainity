<?php

namespace Tests\Feature;

use App\Models\Completion;
use App\Models\CurriculumActivityProgress;
use App\Models\CurriculumAttempt;
use App\Models\CurriculumEntity;
use App\Models\CurriculumImportRun;
use App\Models\CurriculumPackage;
use App\Models\User;
use App\Services\CanonicalCurriculumRepository;
use App\Services\Curriculum\CanonicalCurriculumImporter;
use App\Services\Curriculum\CanonicalPackage;
use App\Services\Curriculum\CanonicalPackageReader;
use App\Services\Curriculum\CurriculumAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class CanonicalCurriculumImportTest extends TestCase
{
    use RefreshDatabase;

    private string $artifactRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artifactRoot = storage_path('framework/testing/curriculum-import-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->artifactRoot);
        File::copy(config('curriculum.standalone_output'), $this->artifactRoot.'/standalone.html');

        config([
            'curriculum.standalone_output' => $this->artifactRoot.'/standalone.html',
            'curriculum.report_directory' => $this->artifactRoot.'/reports',
            'curriculum.rollback_directory' => $this->artifactRoot.'/rollbacks',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->artifactRoot);

        parent::tearDown();
    }

    public function test_dry_run_reports_full_diff_without_writing_database_or_outputs(): void
    {
        $source = app(CanonicalPackageReader::class)->read();
        $outputHash = hash_file('sha256', config('curriculum.standalone_output'));

        $plan = app(CanonicalCurriculumImporter::class)->plan($source);

        $this->assertSame('create', $plan['status']);
        $this->assertSame(7, $plan['count_diff']['chapters']['delta']);
        $this->assertSame(count($source->sourceFiles) + count($source->entities) + count($source->links) + 1, $plan['writes_planned']);
        $this->assertDatabaseCount('curriculum_packages', 0);
        $this->assertSame($outputHash, hash_file('sha256', config('curriculum.standalone_output')));
    }

    public function test_import_is_lossless_verified_and_idempotent(): void
    {
        $source = app(CanonicalPackageReader::class)->read();
        $importer = app(CanonicalCurriculumImporter::class);

        $first = $importer->import($source);
        $second = $importer->import($source);
        $verification = $importer->verify($source);

        $this->assertSame('imported', $first['status']);
        $this->assertSame('no_changes', $second['status']);
        $this->assertSame('verified', $verification['status']);
        $this->assertDatabaseCount('curriculum_packages', 1);
        $this->assertDatabaseCount('curriculum_source_files', count($source->sourceFiles));
        $this->assertDatabaseCount('curriculum_entities', count($source->entities));
        $this->assertDatabaseCount('curriculum_import_runs', 2);
        $this->assertSame($source->counts, $verification['counts']);
        $this->assertSame($source->evidence['standalone']['sha256'], $verification['standalone_sha256']);
        $this->assertTrue(CurriculumPackage::active()?->is_active);

        $sourceSection = collect($source->entities)->firstWhere('code', 'HSP-C02-LS-04');
        $storedSection = CurriculumEntity::query()->where('code', 'HSP-C02-LS-04')->sole();
        $this->assertSame($sourceSection['payload']['blocks'], $storedSection->payload['blocks']);
        $this->assertSame(21, CurriculumEntity::query()->where('entity_type', 'lesson-section')->get()->sum(
            static fn (CurriculumEntity $section): int => collect($section->payload['blocks'])->where('type', 'source_table')->count(),
        ));
        $this->assertSame(18, CurriculumEntity::query()->where('entity_type', 'lesson-section')->get()->sum(
            static fn (CurriculumEntity $section): int => collect($section->payload['blocks'])->where('type', 'external_link')->sum(
                static fn (array $block): int => count($block['links']),
            ),
        ));
    }

    public function test_recorded_rollback_restores_the_pre_import_database_and_standalone(): void
    {
        $source = app(CanonicalPackageReader::class)->read();
        $importer = app(CanonicalCurriculumImporter::class);
        $beforeStandalone = hash_file('sha256', config('curriculum.standalone_output'));

        $import = $importer->import($source);
        $rollback = $importer->rollback($import['rollback']['path']);

        $this->assertSame('rolled_back', $rollback['status']);
        $this->assertDatabaseCount('curriculum_packages', 0);
        $this->assertDatabaseCount('curriculum_entities', 0);
        $this->assertSame($beforeStandalone, hash_file('sha256', config('curriculum.standalone_output')));
        $this->assertDatabaseCount('curriculum_import_runs', 2);
    }

    public function test_failed_import_restores_every_database_table(): void
    {
        $source = app(CanonicalPackageReader::class)->read();
        $duplicateLinks = $source->links;
        $duplicateLinks[] = $duplicateLinks[0];
        $invalid = new CanonicalPackage(
            root: $source->root,
            metadata: $source->metadata,
            sourceFiles: $source->sourceFiles,
            entities: $source->entities,
            links: $duplicateLinks,
            counts: $source->counts,
            evidence: $source->evidence,
            treeSha256: $source->treeSha256,
            byteCount: $source->byteCount,
        );

        try {
            app(CanonicalCurriculumImporter::class)->import($invalid);
            $this->fail('The duplicate relationship did not fail the import.');
        } catch (\Throwable) {
            $this->assertDatabaseCount('curriculum_packages', 0);
            $this->assertDatabaseCount('curriculum_entities', 0);
            $this->assertSame('failed_and_restored', CurriculumImportRun::query()->sole()->status);
        }
    }

    public function test_reader_rejects_a_mutated_package_before_any_import(): void
    {
        $copy = $this->artifactRoot.'/mutated-package';
        File::copyDirectory(config('curriculum.package_path'), $copy);
        $chapterPath = $copy.'/chapters/HSP-C01/chapter.json';
        File::put($chapterPath, str_replace('HSP-C01', 'HSP-X01', File::get($chapterPath)));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Checksum mismatch for canonical package tree');

        app(CanonicalPackageReader::class)->read($copy);
    }

    public function test_import_maps_24_legacy_reveal_completions_without_granting_current_completion(): void
    {
        $source = app(CanonicalPackageReader::class)->read();
        $learner = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $legacyPackage = CurriculumPackage::create([
            'package_name' => 'hospitrainity',
            'content_version' => '0.3.0-draft',
            'schema_version' => '1.0.0',
            'namespace_uuid' => 'cc9dd546-ebaf-51c7-b86f-307d16a22f42',
            'lifecycle_status' => 'draft',
            'source_path' => 'curriculum/hospitrainity/0.3.0-draft',
            'source_tree_sha256' => str_repeat('a', 64),
            'source_file_count' => 0,
            'source_byte_count' => 0,
            'counts' => [],
            'projection_meta' => [],
            'laravel_projection_sha256' => str_repeat('b', 64),
            'standalone_sha256' => str_repeat('c', 64),
            'is_active' => true,
            'imported_at' => now(),
        ]);
        $legacyActivities = collect($source->entities)
            ->where('entity_type', 'activity')
            ->reject(static fn (array $entity): bool => $entity['code'] === 'HSP-C01-ACT-BASELINE')
            ->take(24)
            ->values();
        $this->assertCount(24, $legacyActivities);

        foreach ($legacyActivities as $position => $entity) {
            $activity = CurriculumEntity::create([
                'curriculum_package_id' => $legacyPackage->id,
                'entity_uuid' => $entity['entity_uuid'],
                'code' => $entity['code'],
                'entity_type' => 'activity',
                'parent_code' => $entity['parent_code'],
                'position' => $position + 1,
                'lifecycle_status' => 'published',
                'content_version' => '0.3.0-draft',
                'source_path' => 'legacy/activities/'.$entity['code'].'.json',
                'source_sha256' => $entity['source_sha256'],
                'payload' => $entity['payload'],
            ]);
            Completion::create([
                'user_id' => $learner->id,
                'completable_type' => CurriculumEntity::class,
                'completable_id' => $activity->id,
            ]);
        }

        app(CanonicalCurriculumImporter::class)->import($source);

        $mapped = CurriculumActivityProgress::query()->where('user_id', $learner->id)->get();
        $this->assertCount(24, $mapped);
        $this->assertTrue($mapped->every(static fn (CurriculumActivityProgress $progress): bool => $progress->content_version === '0.3.0-draft'
            && $progress->legacy_status === 'legacy_reveal_only'
            && $progress->viewed_at !== null
            && $progress->started_at !== null
            && $progress->attempted_at === null
            && $progress->completed_at === null
        ));
        $this->assertSame(0, app(CanonicalCurriculumRepository::class)->overallForUsers(collect([$learner]))[$learner->id]);

        $this->actingAs($learner)
            ->get(route('curriculum.activities.show', $legacyActivities->first()['code']))
            ->assertOk()
            ->assertSee('A legacy reveal-only completion exists.')
            ->assertSee('does not count as a completed response attempt for this version.');
    }

    public function test_repair_projection_restores_canonical_rows_without_losing_completion_or_attempt_history(): void
    {
        $source = app(CanonicalPackageReader::class)->read();
        $importer = app(CanonicalCurriculumImporter::class);
        $importer->import($source);
        $learner = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $activity = CurriculumEntity::query()->where('code', 'HSP-C02-ACT-QUIZ')->sole();
        $originalActivityId = $activity->id;
        $prompts = CurriculumEntity::query()->where('entity_type', 'prompt-item')->where('parent_code', $activity->code)->get();
        $answers = CurriculumEntity::query()->where('entity_type', 'answer-model')->whereIn('parent_code', $prompts->pluck('code'))->get()->keyBy('parent_code');
        $responses = $prompts->mapWithKeys(static fn (CurriculumEntity $prompt): array => [
            $prompt->code => $answers[$prompt->code]->payload['correct_choice_ids'][0],
        ])->all();
        app(CurriculumAttemptService::class)->submit($learner, $activity->code, [
            'attempt_key' => (string) Str::uuid(),
            'intent' => 'check',
            'responses' => $responses,
            'self_checks' => [],
        ]);
        Completion::create([
            'user_id' => $learner->id,
            'completable_type' => CurriculumEntity::class,
            'completable_id' => $originalActivityId,
        ]);
        $beforeAttemptId = CurriculumAttempt::query()->sole()->id;

        $corruptPayload = $activity->payload;
        $corruptPayload['title'] = 'Injected projection corruption';
        DB::table('curriculum_entities')
            ->where('id', $activity->id)
            ->update(['payload' => json_encode($corruptPayload, JSON_THROW_ON_ERROR)]);
        $this->assertSame('repair_projection', $importer->plan($source)['status']);

        $repair = $importer->import($source);

        $this->assertSame('imported', $repair['status']);
        $repairedActivity = CurriculumEntity::query()->where('code', $activity->code)->sole();
        $this->assertSame($originalActivityId, $repairedActivity->id);
        $this->assertSame('Step 7. Quiz', $repairedActivity->payload['title']);
        $this->assertDatabaseHas('completions', [
            'user_id' => $learner->id,
            'completable_type' => CurriculumEntity::class,
            'completable_id' => $originalActivityId,
        ]);
        $this->assertDatabaseHas('curriculum_attempts', ['id' => $beforeAttemptId, 'state' => 'completed']);
        $this->assertDatabaseCount('curriculum_responses', 8);
        $this->assertDatabaseCount('curriculum_attempt_events', 3);
        $this->assertNotNull(CurriculumActivityProgress::query()->where('activity_code', $activity->code)->sole()->completed_at);
        $this->assertSame('verified', $importer->verify($source)['status']);

        $rollback = $importer->rollback($repair['rollback']['path']);
        $this->assertSame('rolled_back', $rollback['status']);
        $this->assertSame('Injected projection corruption', CurriculumEntity::query()->where('code', $activity->code)->sole()->payload['title']);
        $this->assertDatabaseHas('curriculum_attempts', ['id' => $beforeAttemptId, 'state' => 'completed']);
        $this->assertDatabaseCount('curriculum_responses', 8);
        $this->assertDatabaseCount('curriculum_attempt_events', 3);
        $this->assertNotNull(CurriculumActivityProgress::query()->where('activity_code', $activity->code)->sole()->completed_at);
    }
}
