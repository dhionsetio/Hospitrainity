<?php

namespace Tests\Feature;

use App\Enums\CurriculumDraftStatus;
use App\Enums\UserRole;
use App\Models\CurriculumActivityProgress;
use App\Models\CurriculumAttempt;
use App\Models\CurriculumDraft;
use App\Models\CurriculumDraftBlock;
use App\Models\CurriculumDraftEntity;
use App\Models\CurriculumPackage;
use App\Models\User;
use App\Services\CanonicalCurriculumRepository;
use App\Services\Curriculum\CanonicalCurriculumImporter;
use App\Services\Curriculum\CanonicalPackageReader;
use App\Services\Curriculum\CurriculumDraftPreviewRepository;
use App\Services\Curriculum\CurriculumDraftReview;
use App\Services\Curriculum\CurriculumDraftWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class CurriculumDraftAuthoringTest extends TestCase
{
    use RefreshDatabase;

    private string $artifactRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artifactRoot = storage_path('framework/testing/curriculum-drafts-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->artifactRoot);
        File::copy(config('curriculum.standalone_output'), $this->artifactRoot.'/standalone.html');
        config([
            'curriculum.standalone_output' => $this->artifactRoot.'/standalone.html',
            'curriculum.report_directory' => $this->artifactRoot.'/reports',
            'curriculum.rollback_directory' => $this->artifactRoot.'/rollbacks',
            'curriculum.draft_validation_directory' => $this->artifactRoot.'/validation',
            'curriculum.published_package_directory' => $this->artifactRoot.'/published',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->artifactRoot);
        parent::tearDown();
    }

    public function test_draft_routes_are_role_scoped_and_publication_does_not_exist_in_admin_namespace(): void
    {
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);

        $this->get(route('admin.curriculum-drafts.index'))->assertRedirect(route('login'));
        $this->actingAs($learner)->get(route('admin.curriculum-drafts.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.curriculum-drafts.index'))->assertOk();
        $this->actingAs($superadmin)->get(route('superadmin.curriculum-drafts.index'))->assertOk();
        $this->assertFalse(Route::has('admin.curriculum-drafts.publish'));
        $this->assertFalse(Route::has('admin.curriculum-drafts.approve'));
        $this->assertTrue(Route::has('superadmin.curriculum-drafts.publish'));
    }

    public function test_clone_is_isolated_and_stale_editor_cannot_overwrite_a_newer_revision(): void
    {
        $this->importActive();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $activeBefore = CurriculumPackage::active();
        $draft = $this->cloneDraft($admin);

        $this->assertSame($activeBefore->id, CurriculumPackage::active()?->id);
        $this->assertSame($activeBefore->entities()->count(), $draft->entities()->count());
        $this->assertSame(774, CurriculumDraftBlock::query()->where('curriculum_draft_id', $draft->id)->count());
        $chapter = CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)->where('code', 'HSP-C01')->sole();

        $payload = [
            'draft_revision' => 1,
            'entity_revision' => 1,
            'title' => 'Reviewed customer-care introduction',
            'position' => 1,
        ];
        $this->actingAs($admin)->patch(route('admin.curriculum-drafts.entities.update', [$draft, $chapter]), $payload)
            ->assertRedirect()->assertSessionHas('success');
        $this->actingAs($admin)->patch(route('admin.curriculum-drafts.entities.update', [$draft, $chapter]), $payload)
            ->assertStatus(409)->assertSessionHasErrors('revision');

        $this->assertSame('Reviewed customer-care introduction', $chapter->fresh()->payload['title']);
        $this->assertSame(2, $chapter->fresh()->revision);
        $this->assertSame(2, $draft->fresh()->revision);
        $this->assertSame($activeBefore->source_tree_sha256, CurriculumPackage::active()?->source_tree_sha256);
    }

    public function test_preview_reuses_canonical_views_without_recording_progress_or_attempts(): void
    {
        $this->importActive();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $draft = $this->cloneDraft($admin);

        $this->actingAs($admin)
            ->get(route('admin.curriculum-drafts.preview.activities.show', [$draft, 'HSP-C02-ACT-PRACTICE']))
            ->assertOk()
            ->assertSee(__('admin.draft_preview'))
            ->assertSee('preview not recorded')
            ->assertSee('HSP-C02-PR-I1');

        $this->assertDatabaseCount((new CurriculumAttempt)->getTable(), 0);
        $this->assertDatabaseCount((new CurriculumActivityProgress)->getTable(), 0);

        $live = app(CanonicalCurriculumRepository::class);
        $preview = app(CurriculumDraftPreviewRepository::class);
        $liveChapter = $live->chapter('HSP-C02');
        $draftChapter = $preview->chapter($draft, 'HSP-C02');
        $this->assertSame($liveChapter['title'], $draftChapter['title']);
        $this->assertSame($liveChapter['outcomes']->all(), $draftChapter['outcomes']->all());
        $this->assertSame($liveChapter['sections']->all(), $draftChapter['sections']->map(fn (array $section): array => array_replace($section, ['status' => 'published']))->all());

        $liveSection = $live->section('HSP-C02-LS-08');
        $draftSection = $preview->section($draft, 'HSP-C02-LS-08');
        $this->assertSame($liveSection['blocks'], $draftSection['blocks']);
        $this->assertSame($liveSection['chapter'], $draftSection['chapter']);
        $this->assertSame($liveSection['navigation'], $draftSection['navigation']);

        $liveActivity = $live->activity('HSP-C02-ACT-PRACTICE', $admin);
        $draftActivity = $preview->activity($draft, 'HSP-C02-ACT-PRACTICE');
        $this->assertSame($liveActivity['metadata'], $draftActivity['metadata']);
        $this->assertSame($liveActivity['prompts']->all(), $draftActivity['prompts']->all());
        $this->assertSame($liveActivity['rubric'], $draftActivity['rubric']);
    }

    public function test_complete_clone_validates_into_review_and_only_superadmin_can_approve(): void
    {
        $this->importActive();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);
        $draft = $this->cloneDraft($admin);

        $this->actingAs($admin)->post(route('admin.curriculum-drafts.validate', $draft), ['draft_revision' => 1])
            ->assertRedirect()->assertSessionHas('success');
        $draft->refresh();
        $this->assertSame(CurriculumDraftStatus::InReview, $draft->status);
        $this->assertSame('valid', $draft->validation_report['status']);
        $this->assertSame(7, $draft->validation_report['counts']['chapters']);
        $this->assertSame(0, $draft->diff_report['summary']['changed']);

        $this->actingAs($superadmin)->post(route('superadmin.curriculum-drafts.approve', $draft), [
            'draft_revision' => $draft->revision,
            'reason' => 'Canonical validation and source-aware diff were reviewed.',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(CurriculumDraftStatus::Approved, $draft->fresh()->status);
    }

    public function test_typed_block_validation_reordering_archive_and_restore_are_revision_checked(): void
    {
        $this->importActive();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $draft = $this->cloneDraft($admin);
        $section = CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)->where('code', 'HSP-C02-LS-08')->sole();
        $before = $section->blocks()->count();

        $this->actingAs($admin)->post(route('admin.curriculum-drafts.blocks.store', [$draft, $section]), [
            'draft_revision' => 1, 'entity_revision' => 1, 'block_type' => 'paragraph', 'text' => '',
            'provenance_note' => 'Manual source review is required.',
        ])->assertSessionHasErrors('text');
        $this->assertSame($before, $section->blocks()->count());

        $this->actingAs($admin)->post(route('admin.curriculum-drafts.blocks.store', [$draft, $section]), [
            'draft_revision' => 1, 'entity_revision' => 1, 'block_type' => 'paragraph',
            'text' => 'A reviewed draft-only paragraph.',
            'provenance_note' => 'Manual draft content reviewed against the declared source.',
        ])->assertRedirect()->assertSessionHas('success');
        $block = CurriculumDraftBlock::query()->where('curriculum_draft_entity_id', $section->id)->orderByDesc('id')->firstOrFail();
        $this->assertSame($before + 1, $section->blocks()->count());

        $this->actingAs($admin)->post(route('admin.curriculum-drafts.blocks.move', [$draft, $block]), [
            'draft_revision' => $draft->fresh()->revision, 'block_revision' => $block->revision, 'position' => 1,
        ])->assertRedirect()->assertSessionHas('success');
        $block->refresh();
        $this->assertSame(1, $block->position);

        $this->actingAs($admin)->post(route('admin.curriculum-drafts.blocks.archive', [$draft, $block]), [
            'draft_revision' => $draft->fresh()->revision, 'block_revision' => $block->revision,
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertNotNull($block->fresh()->archived_at);

        $block->refresh();
        $this->actingAs($admin)->post(route('admin.curriculum-drafts.blocks.restore', [$draft, $block]), [
            'draft_revision' => $draft->fresh()->revision, 'block_revision' => $block->revision,
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertNull($block->fresh()->archived_at);
        $this->assertSame('A reviewed draft-only paragraph.', $block->fresh()->payload['text']);
    }

    public function test_archiving_a_section_cascades_only_inside_draft_and_restore_is_explicit(): void
    {
        $this->importActive();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $draft = $this->cloneDraft($admin);
        $section = CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)->where('code', 'HSP-C02-LS-08')->sole();
        $activeCount = CurriculumPackage::active()->entities()->count();

        $this->actingAs($admin)->post(route('admin.curriculum-drafts.entities.archive', [$draft, $section]), [
            'draft_revision' => 1, 'entity_revision' => 1,
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertNotNull($section->fresh()->archived_at);
        $this->assertGreaterThan(1, CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)->whereNotNull('archived_at')->count());
        $this->assertSame($activeCount, CurriculumPackage::active()->entities()->count());

        $section->refresh();
        $this->actingAs($admin)->post(route('admin.curriculum-drafts.entities.restore', [$draft, $section]), [
            'draft_revision' => $draft->fresh()->revision, 'entity_revision' => $section->revision,
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertNull($section->fresh()->archived_at);
        $this->assertGreaterThan(0, CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)->whereNotNull('archived_at')->count());
    }

    public function test_entity_order_parent_changes_and_archive_restore_invariants_remain_consistent(): void
    {
        $this->importActive();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $draft = $this->cloneDraft($admin);
        $workspace = app(CurriculumDraftWorkspace::class);

        $created = $workspace->createEntity($draft, $admin, 1, [
            'entity_type' => 'chapter',
            'code' => 'HSP-C99',
            'title' => 'Draft-only insertion',
            'position' => 2,
        ]);
        $this->assertSame(2, $created->position);
        $this->assertSequentialEntityPositions($draft, 'chapter');

        $section = CurriculumDraftEntity::query()
            ->where('curriculum_draft_id', $draft->id)->where('code', 'HSP-C02-LS-08')->sole();
        $updated = $workspace->updateEntity($draft->fresh(), $section, $admin, 2, $section->revision, [
            'title' => $section->payload['title'],
            'parent_code' => 'HSP-C01',
            'position' => 1,
        ]);
        $this->assertSame('HSP-C01', $updated->parent_code);
        $this->assertSame('chapters/HSP-C01/sections/HSP-C02-LS-08.json', $updated->source_path);
        $this->assertSequentialEntityPositions($draft, 'lesson-section', 'HSP-C01');
        $this->assertSequentialEntityPositions($draft, 'lesson-section', 'HSP-C02');

        $workspace->archiveEntity($draft->fresh(), $updated, $admin, 3, $updated->revision);
        $updated->refresh();
        $this->assertSequentialEntityPositions($draft, 'lesson-section', 'HSP-C01');

        try {
            $workspace->updateEntity($draft->fresh(), $updated, $admin, 4, $updated->revision, [
                'title' => 'Archived edits must fail',
                'parent_code' => 'HSP-C01',
                'position' => 1,
            ]);
            $this->fail('Archived draft entities must not be editable.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('must be restored', $exception->getMessage());
        }
        $this->assertSame(4, $draft->fresh()->revision);

        $parent = CurriculumDraftEntity::query()
            ->where('curriculum_draft_id', $draft->id)->where('code', 'HSP-C01')->sole();
        $workspace->archiveEntity($draft->fresh(), $parent, $admin, 4, $parent->revision);
        $updated->refresh();
        try {
            $workspace->restoreEntity($draft->fresh(), $updated, $admin, 5, $updated->revision);
            $this->fail('A lesson section must not be restored below an archived module.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('parent module', $exception->getMessage());
        }
        $this->assertSame(5, $draft->fresh()->revision);

        $parent->refresh();
        $workspace->restoreEntity($draft->fresh(), $parent, $admin, 5, $parent->revision);
        $updated->refresh();
        $workspace->restoreEntity($draft->fresh(), $updated, $admin, 6, $updated->revision);
        $this->assertNull($updated->fresh()->archived_at);
        $this->assertSequentialEntityPositions($draft, 'chapter');
        $this->assertSequentialEntityPositions($draft, 'lesson-section', 'HSP-C01');
    }

    public function test_archived_blocks_cannot_be_changed_or_restored_below_an_archived_section(): void
    {
        $this->importActive();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $draft = $this->cloneDraft($admin);
        $workspace = app(CurriculumDraftWorkspace::class);
        $section = CurriculumDraftEntity::query()
            ->where('curriculum_draft_id', $draft->id)->where('code', 'HSP-C02-LS-08')->sole();
        $block = $section->blocks()->whereNull('archived_at')->orderBy('position')->firstOrFail();

        $workspace->setBlockArchived($draft, $block, $admin, 1, $block->revision, true);
        $block->refresh();
        $this->assertSequentialBlockPositions($section);

        try {
            $workspace->moveBlock($draft->fresh(), $block, $admin, 2, $block->revision, 1);
            $this->fail('Archived blocks must not be reorderable.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('must be restored', $exception->getMessage());
        }
        $this->assertSame(2, $draft->fresh()->revision);

        $section->refresh();
        $workspace->archiveEntity($draft->fresh(), $section, $admin, 2, $section->revision);
        $block->refresh();
        try {
            $workspace->setBlockArchived($draft->fresh(), $block, $admin, 3, $block->revision, false);
            $this->fail('Blocks must not be restored below an archived lesson section.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('lesson section', $exception->getMessage());
        }
        $this->assertSame(3, $draft->fresh()->revision);
    }

    public function test_empty_workspace_fails_closed_and_returns_to_draft(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $draft = app(CurriculumDraftWorkspace::class)->create($admin, [
            'source' => 'empty', 'content_version' => '0.4.1', 'title' => 'Explicit empty workspace',
        ]);

        $result = app(CurriculumDraftReview::class)->validate($draft, $admin, 1);

        $this->assertSame(CurriculumDraftStatus::Draft, $result->status);
        $this->assertSame('invalid', $result->validation_report['status']);
        $this->assertStringContainsString('empty workspace', strtolower($result->validation_report['errors'][0]['message']));
        $this->assertDatabaseCount('curriculum_packages', 0);
    }

    public function test_publication_creates_an_immutable_version_and_recorded_rollback_restores_prior_delivery(): void
    {
        $this->importActive();
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);
        $prior = CurriculumPackage::active();
        $draft = $this->cloneDraft($superadmin);
        $draft = app(CurriculumDraftReview::class)->validate($draft, $superadmin, 1);
        $draft = app(CurriculumDraftReview::class)->approve($draft, $superadmin, $draft->revision, 'Canonical validation and release diff were reviewed.');

        $this->actingAs($superadmin)->post(route('superadmin.curriculum-drafts.publish', $draft), [
            'draft_revision' => $draft->revision,
        ])->assertRedirect(route('password.confirm'));
        $this->actingAs($superadmin)->withSession($this->passwordConfirmedSession())
            ->post(route('superadmin.curriculum-drafts.publish', $draft), ['draft_revision' => $draft->revision])
            ->assertRedirect()->assertSessionHas('success');

        $draft->refresh();
        $published = CurriculumPackage::active();
        $this->assertSame(CurriculumDraftStatus::Published, $draft->status);
        $this->assertSame('0.4.1', $published?->content_version);
        $this->assertNotSame($prior->id, $published?->id);
        $this->assertFalse($prior->fresh()->is_active);
        $this->assertFileExists($published->source_path.'/package.json');
        $this->assertStringNotContainsString('-draft', $published->content_version);
        $this->assertSame(0, Artisan::call('hospitrainity:curriculum', ['action' => 'verify']));

        $this->actingAs($superadmin)->withSession($this->passwordConfirmedSession())
            ->post(route('superadmin.curriculum-drafts.rollback', $draft), ['draft_revision' => $draft->revision])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame($prior->content_version, CurriculumPackage::active()?->content_version);
        $this->assertNotNull($draft->fresh()->base_package_id);
        $this->assertGreaterThan(0, CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)->whereNotNull('source_entity_id')->count());
        $this->assertDatabaseHas('curriculum_draft_events', ['curriculum_draft_id' => $draft->id, 'event_type' => 'publication_rolled_back']);
    }

    private function importActive(): void
    {
        app(CanonicalCurriculumImporter::class)->import(app(CanonicalPackageReader::class)->read());
    }

    private function cloneDraft(User $actor): CurriculumDraft
    {
        return app(CurriculumDraftWorkspace::class)->create($actor, [
            'source' => 'clone', 'content_version' => '0.4.1', 'title' => 'ADM-2 canonical review candidate',
        ]);
    }

    private function assertSequentialEntityPositions(CurriculumDraft $draft, string $type, ?string $parentCode = null): void
    {
        $entities = CurriculumDraftEntity::query()
            ->where('curriculum_draft_id', $draft->id)
            ->where('entity_type', $type)
            ->where('parent_code', $parentCode)
            ->whereNull('archived_at')
            ->orderBy('position')->orderBy('id')->get();
        $this->assertSame(range(1, $entities->count()), $entities->pluck('position')->all());
        if ($type === 'chapter') {
            $this->assertSame(range(1, $entities->count()), $entities->pluck('payload')->pluck('module')->all());
        } elseif ($type === 'lesson-section') {
            $this->assertSame(range(1, $entities->count()), $entities->pluck('payload')->pluck('order')->all());
        }
    }

    private function assertSequentialBlockPositions(CurriculumDraftEntity $section): void
    {
        $blocks = $section->blocks()->whereNull('archived_at')->orderBy('position')->orderBy('id')->get();
        $this->assertSame(range(1, $blocks->count()), $blocks->pluck('position')->all());
        $this->assertSame(range(1, $blocks->count()), $blocks->pluck('payload')->pluck('order')->all());
    }

    /** @return array<string, int> */
    private function passwordConfirmedSession(): array
    {
        return ['auth.password_confirmed_at' => time()];
    }
}
