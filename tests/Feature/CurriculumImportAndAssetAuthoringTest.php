<?php

namespace Tests\Feature;

use App\Enums\CurriculumImportStatus;
use App\Enums\UserRole;
use App\Models\CurriculumAssetBlob;
use App\Models\CurriculumDraft;
use App\Models\CurriculumDraftBlock;
use App\Models\CurriculumDraftEntity;
use App\Models\CurriculumPackage;
use App\Models\User;
use App\Services\Curriculum\CanonicalCurriculumImporter;
use App\Services\Curriculum\CanonicalPackageReader;
use App\Services\Curriculum\CurriculumAssetWorkspace;
use App\Services\Curriculum\CurriculumDraftReview;
use App\Services\Curriculum\CurriculumDraftWorkspace;
use App\Services\Curriculum\CurriculumImportCompiler;
use App\Services\Curriculum\CurriculumImportWorkspace;
use App\Services\Curriculum\CurriculumUploadInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class CurriculumImportAndAssetAuthoringTest extends TestCase
{
    use RefreshDatabase;

    private string $artifactRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artifactRoot = storage_path('framework/testing/adm3-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->artifactRoot);
        File::copy(config('curriculum.standalone_output'), $this->artifactRoot.'/standalone.html');
        config([
            'filesystems.disks.curriculum_private.root' => $this->artifactRoot.'/private',
            'curriculum.standalone_output' => $this->artifactRoot.'/standalone.html',
            'curriculum.report_directory' => $this->artifactRoot.'/reports',
            'curriculum.rollback_directory' => $this->artifactRoot.'/rollbacks',
            'curriculum.draft_validation_directory' => $this->artifactRoot.'/validation',
            'curriculum.published_package_directory' => $this->artifactRoot.'/published',
        ]);
        app('filesystem')->forgetDisk('curriculum_private');
    }

    protected function tearDown(): void
    {
        app('filesystem')->forgetDisk('curriculum_private');
        File::deleteDirectory($this->artifactRoot);
        parent::tearDown();
    }

    public function test_import_and_asset_routes_are_role_scoped(): void
    {
        $this->importActive();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $draft = $this->cloneDraft($admin);

        $this->actingAs($learner)->post(route('admin.curriculum-drafts.imports.store', $draft))->assertForbidden();
        $this->actingAs($learner)->post(route('admin.curriculum-drafts.assets.store', $draft))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.curriculum-drafts.imports.store', $draft), [
            'draft_revision' => 1,
            'declared_purpose' => 'Review a proposed source revision.',
            'source' => UploadedFile::fake()->create('source.pdf', 1, 'application/pdf'),
        ])->assertSessionHasErrors('source');
        $this->assertDatabaseCount('curriculum_imports', 0);
        $this->actingAs($admin)->get(route('admin.curriculum-drafts.show', $draft))
            ->assertOk()
            ->assertSee('1. Welcome and Introduction to Customer Care');
    }

    public function test_authority_docx_compiles_to_a_private_dry_run_and_acceptance_changes_only_the_draft(): void
    {
        $authority = $this->authorityDocx();
        if ($authority === null) {
            $this->markTestSkipped('The externally supplied authority DOCX is not available on this machine.');
        }
        $this->importActive();
        Queue::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $draft = $this->cloneDraft($admin);
        $active = CurriculumPackage::active();
        $activeHash = $active->source_tree_sha256;

        $import = app(CurriculumImportWorkspace::class)->queue(
            $draft,
            $admin,
            1,
            new UploadedFile($authority, 'Hospitrainity.docx', null, null, true),
            'Compile the authority document into a reviewable private draft.',
        );
        $this->assertSame(CurriculumImportStatus::Queued, $import->status);
        $this->assertStringNotContainsString('Hospitrainity.docx', (string) $import->source_storage_path);
        $this->assertTrue(Storage::disk('curriculum_private')->exists($import->source_storage_path));

        app(CurriculumImportCompiler::class)->compile($import);
        $import->refresh();
        $this->assertSame(CurriculumImportStatus::Ready, $import->status, json_encode($import->error_report));
        $this->assertSame(7, $import->compiler_report['counts']['chapter']);
        $this->assertSame(85, $import->compiler_report['counts']['lesson-section']);
        $this->assertSame('2.1.0', $import->compiler_report['schema_version']);
        $this->assertSame('Hospitrainity.docx', $import->inventory_report['source']['artifact']);
        $this->assertSame(0, $import->diff_report['summary']['created']);
        $this->assertSame(2, $import->diff_report['summary']['changed'], json_encode($import->diff_report['items']['changed']));
        $this->assertSame(
            ['package.json', 'schemas/content-block.schema.json'],
            array_column($import->diff_report['items']['changed'], 'source_path'),
        );
        $this->assertSame(0, $import->diff_report['summary']['removed']);
        $this->assertSame(0, $import->diff_report['summary']['rejected']);
        $this->assertSame(0, $import->diff_report['summary']['unclassified']);
        $this->assertSame($activeHash, CurriculumPackage::active()?->source_tree_sha256);
        $this->assertDatabaseHas('curriculum_draft_events', [
            'curriculum_draft_id' => $draft->id,
            'event_type' => 'docx_import_ready',
        ]);

        $this->actingAs($admin)->post(route('admin.curriculum-drafts.imports.accept', [$draft, $import]), [
            'draft_revision' => 1, 'import_revision' => $import->revision, 'confirmation' => 'accept',
        ])->assertSessionHasErrors('confirmation');
        $this->assertSame(CurriculumImportStatus::Ready, $import->fresh()->status);
        $this->actingAs($admin)->post(route('admin.curriculum-drafts.imports.accept', [$draft, $import]), [
            'draft_revision' => 1, 'import_revision' => $import->revision, 'confirmation' => 'REPLACE DRAFT',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(CurriculumImportStatus::Accepted, $import->fresh()->status);
        $this->assertSame(7, $draft->entities()->where('entity_type', 'chapter')->count());
        $this->assertSame(774, $draft->blocks()->count());
        $this->assertSame('2.1.0', $draft->fresh()->schema_version);
        $this->assertSame($active->id, CurriculumPackage::active()?->id);
        $this->assertSame($activeHash, CurriculumPackage::active()?->source_tree_sha256);
        $this->assertDatabaseHas('curriculum_draft_events', ['curriculum_draft_id' => $draft->id, 'event_type' => 'docx_import_accepted']);
    }

    public function test_malformed_docx_entries_are_rejected_before_queueing(): void
    {
        $path = $this->artifactRoot.'/unsafe.docx';
        $this->makeDocx($path, ['../escape.txt' => 'not extractable']);
        $upload = new UploadedFile($path, 'unsafe.docx', null, null, true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unsafe entry path');
        app(CurriculumUploadInspector::class)->docx($upload);
    }

    public function test_compiler_failure_deletes_the_rejected_source_and_preserves_active_delivery(): void
    {
        $this->importActive();
        Queue::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $draft = $this->cloneDraft($admin);
        $activeId = CurriculumPackage::active()?->id;
        $path = $this->artifactRoot.'/minimal.docx';
        $this->makeDocx($path);

        $import = app(CurriculumImportWorkspace::class)->queue(
            $draft,
            $admin,
            1,
            new UploadedFile($path, 'minimal.docx', null, null, true),
            'Exercise the fail-closed compiler rejection path.',
        );
        $quarantine = $import->source_storage_path;
        app(CurriculumImportCompiler::class)->compile($import);

        $import->refresh();
        $this->assertSame(CurriculumImportStatus::Failed, $import->status);
        $this->assertNull($import->source_storage_path);
        $this->assertTrue($import->error_report['source_deleted']);
        $this->assertFalse(Storage::disk('curriculum_private')->exists($quarantine));
        $this->assertSame($activeId, CurriculumPackage::active()?->id);
        $this->assertSame(1, $draft->fresh()->revision);
        $this->assertDatabaseHas('curriculum_draft_events', [
            'curriculum_draft_id' => $draft->id,
            'event_type' => 'docx_import_failed',
        ]);
    }

    public function test_assets_are_content_checked_deduplicated_traceable_and_rendered_only_through_protected_routes(): void
    {
        $this->importActive();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $draft = $this->cloneDraft($admin);
        $path = $this->artifactRoot.'/pixel.png';
        File::put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
        $workspace = app(CurriculumAssetWorkspace::class);
        $data = [
            'display_name' => 'Front desk position diagram',
            'accessibility_text' => 'A one-pixel test image representing a front desk diagram.',
            'rights_basis' => 'Generated test fixture owned by the test suite.',
            'source_url' => null,
        ];
        $asset = $workspace->store($draft, $admin, 1, new UploadedFile($path, 'diagram.png', null, null, true), $data);
        $workspace->store($draft, $admin, 1, new UploadedFile($path, 'duplicate.png', null, null, true), array_replace($data, ['display_name' => 'Duplicate logical use']));
        $this->assertDatabaseCount('curriculum_assets', 2);
        $this->assertSame(1, CurriculumAssetBlob::query()->count());
        $this->assertStringNotContainsString('diagram.png', $asset->blob->storage_path);

        $section = CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)->where('code', 'HSP-C02-LS-08')->sole();
        $this->actingAs($admin)->post(route('admin.curriculum-drafts.blocks.store', [$draft, $section]), [
            'draft_revision' => 1,
            'entity_revision' => 1,
            'block_type' => 'paragraph',
            'text' => 'Review the attached front desk diagram.',
            'provenance_note' => 'Administrative teaching aid; source fidelity review is still required.',
            'asset_public_id' => $asset->public_id,
        ])->assertRedirect()->assertSessionHas('success');
        $block = CurriculumDraftBlock::query()->where('curriculum_draft_id', $draft->id)->latest('id')->firstOrFail();
        $this->assertSame($asset->id, $block->curriculum_asset_id);
        $this->assertSame('admin_authored', $block->payload['provenance_kind']);
        $this->assertSame($asset->blob->sha256, $block->payload['asset']['sha256']);

        $this->actingAs($admin)->get(route('admin.curriculum-drafts.preview.sections.show', [$draft, $section->code]))
            ->assertOk()->assertSee($asset->display_name)->assertSee($asset->accessibility_text);
        $this->actingAs($admin)->get(route('admin.curriculum-drafts.assets.show', [$draft, $asset]))
            ->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');

        $validated = app(CurriculumDraftReview::class)->validate($draft->fresh(), $admin, 2);
        $this->assertSame('valid', $validated->validation_report['status'], json_encode($validated->validation_report));
        $this->assertSame(775, $draft->blocks()->count());
        $this->assertSame(CurriculumPackage::active()?->id, $draft->base_package_id);
    }

    public function test_field_specific_table_and_link_editors_reject_malformed_canonical_shapes(): void
    {
        $this->importActive();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $draft = $this->cloneDraft($admin);
        $section = CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)->where('code', 'HSP-C02-LS-08')->sole();

        $this->actingAs($admin)->post(route('admin.curriculum-drafts.blocks.store', [$draft, $section]), [
            'draft_revision' => 1, 'entity_revision' => 1, 'block_type' => 'source_table',
            'caption' => 'Useful expressions', 'table_header' => "Phrase\tPurpose", 'table_rows' => 'Only one cell',
            'provenance_note' => 'Manual table under source review.',
        ])->assertSessionHasErrors('table_rows');
        $this->actingAs($admin)->post(route('admin.curriculum-drafts.blocks.store', [$draft, $section]), [
            'draft_revision' => 1, 'entity_revision' => 1, 'block_type' => 'external_link',
            'link_text' => ['Unsafe reference'], 'link_target' => ['javascript:alert(1)'],
            'provenance_note' => 'Manual link under source review.',
        ])->assertSessionHasErrors('link_target.0');
        $this->assertSame(774, $draft->blocks()->count());
    }

    private function importActive(): void
    {
        app(CanonicalCurriculumImporter::class)->import(app(CanonicalPackageReader::class)->read());
    }

    private function cloneDraft(User $actor): CurriculumDraft
    {
        return app(CurriculumDraftWorkspace::class)->create($actor, [
            'source' => 'clone', 'content_version' => '0.4.1', 'title' => 'ADM-3 canonical import candidate',
        ]);
    }

    private function authorityDocx(): ?string
    {
        $path = 'C:/Users/dhion/Desktop/Documents/000 - Thesis Dhion Setio/Revisi 30 Juni 2026/Learning Materials - Fixed/Hospitrainity.docx';

        return is_file($path) ? $path : null;
    }

    /** @param array<string, string> $extraEntries */
    private function makeDocx(string $path, array $extraEntries = []): void
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Test document</w:t></w:r></w:p></w:body></w:document>');
        foreach ($extraEntries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();
    }
}
