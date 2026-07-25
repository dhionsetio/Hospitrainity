<?php

namespace Tests\Feature;

use App\Enums\CurriculumApprovalGate;
use App\Enums\CurriculumReleaseState;
use App\Enums\UserRole;
use App\Models\CurriculumPackage;
use App\Models\CurriculumRelease;
use App\Models\User;
use App\Services\Curriculum\CanonicalCurriculumImporter;
use App\Services\Curriculum\CanonicalPackage;
use App\Services\Curriculum\CanonicalPackageReader;
use App\Services\Curriculum\CurriculumReleaseGuard;
use App\Services\Curriculum\CurriculumReleaseLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class CurriculumReleaseGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_refuses_draft_import_before_database_writes(): void
    {
        $this->app['env'] = 'production';
        $source = app(CanonicalPackageReader::class)->read();

        try {
            app(CanonicalCurriculumImporter::class)->import($source);
            $this->fail('A draft curriculum import unexpectedly succeeded in production.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('lifecycle-draft', $exception->getMessage());
        }

        $this->assertDatabaseCount('curriculum_packages', 0);
        $this->assertDatabaseCount('curriculum_releases', 0);
        $this->assertDatabaseCount('curriculum_import_runs', 0);
    }

    public function test_imported_draft_is_preview_only_and_labeled_only_for_system_admin_learner_review(): void
    {
        $source = app(CanonicalPackageReader::class)->read();
        app(CanonicalCurriculumImporter::class)->import($source);

        $release = CurriculumRelease::query()->sole();
        $this->assertSame(CurriculumReleaseState::Draft, $release->state);
        $this->assertTrue($release->preview_only);
        $this->assertSame($source->treeSha256, $release->source_tree_sha256);
        $this->assertDatabaseCount('curriculum_release_approvals', 0);

        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->actingAs($learner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSeeText('Non-production draft preview')
            ->assertDontSeeText('must not be treated as a production release');

        $systemAdmin = User::factory()->create(['role' => UserRole::Superadmin]);
        $this->actingAs($systemAdmin)
            ->post(route('work-context.store'), ['role' => 'learner'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Non-production draft preview')
            ->assertSeeText('must not be treated as a production release');
    }

    public function test_production_delivery_refuses_an_existing_active_draft(): void
    {
        $source = app(CanonicalPackageReader::class)->read();
        app(CanonicalCurriculumImporter::class)->import($source);
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Production delivery refused');

        CurriculumPackage::active();
    }

    public function test_replacement_import_is_contained_until_release_gates_close(): void
    {
        $source = app(CanonicalPackageReader::class)->read();
        app(CanonicalCurriculumImporter::class)->import($source);
        $activeId = CurriculumPackage::query()->where('is_active', true)->value('id');
        $metadata = $source->metadata;
        $metadata['content_version'] = '0.5.0';
        $metadata['status'] = 'published';
        $replacement = new CanonicalPackage(
            root: $source->root,
            metadata: $metadata,
            sourceFiles: $source->sourceFiles,
            entities: $source->entities,
            links: $source->links,
            counts: $source->counts,
            evidence: $source->evidence,
            treeSha256: hash('sha256', 'unapproved-replacement'),
            byteCount: $source->byteCount,
        );

        try {
            app(CanonicalCurriculumImporter::class)->import($replacement);
            $this->fail('An unapproved replacement unexpectedly became active.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('replacement package cannot become active', $exception->getMessage());
        }

        $this->assertSame($activeId, CurriculumPackage::query()->where('is_active', true)->value('id'));
        $this->assertDatabaseCount('curriculum_packages', 1);
        $this->assertDatabaseCount('curriculum_import_runs', 1);
    }

    public function test_non_draft_package_with_preview_only_release_keeps_the_reviewer_warning(): void
    {
        $source = app(CanonicalPackageReader::class)->read();
        app(CanonicalCurriculumImporter::class)->import($source);
        CurriculumPackage::query()->where('is_active', true)->update(['lifecycle_status' => 'published']);

        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->actingAs($learner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSeeText('Non-production draft preview');

        $systemAdmin = User::factory()->create(['role' => UserRole::Superadmin]);
        $this->actingAs($systemAdmin)
            ->post(route('work-context.store'), ['role' => 'learner'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Non-production draft preview')
            ->assertSeeText('must not be treated as a production release');
    }

    public function test_delivery_refuses_release_checksum_corruption_even_outside_production(): void
    {
        $source = app(CanonicalPackageReader::class)->read();
        app(CanonicalCurriculumImporter::class)->import($source);
        CurriculumRelease::query()->where('curriculum_package_id', CurriculumPackage::query()->value('id'))
            ->toBase()
            ->update(['source_tree_sha256' => hash('sha256', 'corrupted-release-reference')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('source checksums do not match');

        CurriculumPackage::active();
    }

    public function test_production_delivery_revalidates_complete_approval_evidence(): void
    {
        $source = app(CanonicalPackageReader::class)->read();
        app(CanonicalCurriculumImporter::class)->import($source);
        $package = CurriculumPackage::query()->sole();
        $package->forceFill(['lifecycle_status' => 'published'])->save();
        $release = CurriculumRelease::query()->sole();
        $recorder = User::factory()->create(['role' => UserRole::Superadmin]);
        $lifecycle = app(CurriculumReleaseLifecycle::class);
        $release = $lifecycle->transition(
            $release,
            CurriculumReleaseState::Draft,
            CurriculumReleaseState::InReview,
            $recorder,
            'Begin formal production-evidence validation.',
        );
        foreach (CurriculumApprovalGate::cases() as $gate) {
            $lifecycle->approveGate(
                $release,
                $gate,
                $recorder,
                'Named '.$gate->value.' reviewer',
                'Recorded external qualification',
                hash('sha256', 'delivery-evidence-'.$gate->value),
            );
        }
        $release = $lifecycle->transition(
            $release,
            CurriculumReleaseState::InReview,
            CurriculumReleaseState::Approved,
            $recorder,
            'All named gates have complete evidence.',
        );
        $lifecycle->transition(
            $release,
            CurriculumReleaseState::Approved,
            CurriculumReleaseState::Active,
            $recorder,
            'Activate the verified test release.',
        );
        DB::table('curriculum_release_approvals')
            ->where('gate', CurriculumApprovalGate::Content->value)
            ->update(['recorded_by_user_id' => null]);
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not have an approved non-draft release');

        CurriculumPackage::active();
    }

    public function test_production_delivery_refuses_active_state_without_activation_evidence(): void
    {
        $source = app(CanonicalPackageReader::class)->read();
        app(CanonicalCurriculumImporter::class)->import($source);
        $package = CurriculumPackage::query()->sole();
        $package->forceFill(['lifecycle_status' => 'published'])->save();
        $release = CurriculumRelease::query()->sole();
        $recorder = User::factory()->create(['role' => UserRole::Superadmin]);
        $lifecycle = app(CurriculumReleaseLifecycle::class);
        $release = $lifecycle->transition(
            $release,
            CurriculumReleaseState::Draft,
            CurriculumReleaseState::InReview,
            $recorder,
            'Begin activation-evidence validation.',
        );
        foreach (CurriculumApprovalGate::cases() as $gate) {
            $lifecycle->approveGate(
                $release,
                $gate,
                $recorder,
                'Named '.$gate->value.' reviewer',
                'Recorded external qualification',
                hash('sha256', 'activation-evidence-'.$gate->value),
            );
        }
        $release = $lifecycle->transition(
            $release,
            CurriculumReleaseState::InReview,
            CurriculumReleaseState::Approved,
            $recorder,
            'All named gates have complete evidence.',
        );
        $release = $lifecycle->transition(
            $release,
            CurriculumReleaseState::Approved,
            CurriculumReleaseState::Active,
            $recorder,
            'Activate the verified test release.',
        );
        DB::table('curriculum_releases')->where('id', $release->id)->update(['activated_at' => null]);
        $this->app['env'] = 'production';

        try {
            CurriculumPackage::active();
            $this->fail('Production delivery accepted an active release without an activation timestamp.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('does not have an approved non-draft release', $exception->getMessage());
        }

        DB::table('curriculum_releases')->where('id', $release->id)->update([
            'activated_at' => $release->activated_at,
        ]);
        DB::table('curriculum_release_events')
            ->where('curriculum_release_id', $release->id)
            ->where('to_state', CurriculumReleaseState::Active->value)
            ->update(['actor_user_id' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not have an approved non-draft release');

        CurriculumPackage::active();
    }

    public function test_production_class_delivery_accepts_a_retired_release_with_complete_activation_evidence(): void
    {
        $source = app(CanonicalPackageReader::class)->read();
        app(CanonicalCurriculumImporter::class)->import($source);
        $package = CurriculumPackage::query()->sole();
        $package->forceFill(['lifecycle_status' => 'published'])->save();
        $release = CurriculumRelease::query()->sole();
        $recorder = User::factory()->create(['role' => UserRole::Superadmin]);
        $lifecycle = app(CurriculumReleaseLifecycle::class);
        $release = $lifecycle->transition(
            $release,
            CurriculumReleaseState::Draft,
            CurriculumReleaseState::InReview,
            $recorder,
            'Begin pinned Class release validation.',
        );
        foreach (CurriculumApprovalGate::cases() as $gate) {
            $lifecycle->approveGate(
                $release,
                $gate,
                $recorder,
                'Named '.$gate->value.' reviewer',
                'Recorded external qualification',
                hash('sha256', 'pinned-class-evidence-'.$gate->value),
            );
        }
        $release = $lifecycle->transition(
            $release,
            CurriculumReleaseState::InReview,
            CurriculumReleaseState::Approved,
            $recorder,
            'All release gates are complete.',
        );
        $release = $lifecycle->transition(
            $release,
            CurriculumReleaseState::Approved,
            CurriculumReleaseState::Active,
            $recorder,
            'Activate the approved release.',
        );
        $lifecycle->transition(
            $release,
            CurriculumReleaseState::Active,
            CurriculumReleaseState::Retired,
            $recorder,
            'A newer package superseded this release.',
        );
        $this->app['env'] = 'production';

        app(CurriculumReleaseGuard::class)
            ->assertPinnedDeliverable($package->fresh('release'));

        $this->addToAssertionCount(1);
    }
}
