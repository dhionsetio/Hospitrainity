<?php

namespace Tests\Feature;

use App\Enums\CurriculumApprovalGate;
use App\Enums\CurriculumReleaseState;
use App\Enums\UserRole;
use App\Models\CurriculumPackage;
use App\Models\CurriculumRelease;
use App\Models\CurriculumReleaseApproval;
use App\Models\User;
use App\Services\Curriculum\CurriculumReleaseLifecycle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class CurriculumReleaseLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_release_cannot_be_approved_until_every_named_gate_has_immutable_evidence(): void
    {
        [$release, $reviewer] = $this->releaseFixture();
        $lifecycle = app(CurriculumReleaseLifecycle::class);
        $release = $lifecycle->transition(
            $release,
            CurriculumReleaseState::Draft,
            CurriculumReleaseState::InReview,
            $reviewer,
            'Submit the immutable package for specialist review.',
        );
        $lifecycle->approveGate(
            $release,
            CurriculumApprovalGate::Content,
            $reviewer,
            'Content Reviewer',
            'Recorded content-review qualification',
            str_repeat('a', 64),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('approval gates are incomplete');

        $lifecycle->transition(
            $release,
            CurriculumReleaseState::InReview,
            CurriculumReleaseState::Approved,
            $reviewer,
            'All required reviewers approved this release.',
        );
    }

    public function test_complete_non_draft_release_can_activate_and_records_append_only_evidence(): void
    {
        [$release, $reviewer] = $this->releaseFixture();
        $lifecycle = app(CurriculumReleaseLifecycle::class);
        $release = $lifecycle->transition(
            $release,
            CurriculumReleaseState::Draft,
            CurriculumReleaseState::InReview,
            $reviewer,
            'Begin formal release review.',
        );

        foreach (CurriculumApprovalGate::cases() as $index => $gate) {
            $lifecycle->approveGate(
                $release,
                $gate,
                $reviewer,
                'Named Reviewer '.($index + 1),
                'Recorded qualification for '.$gate->value,
                hash('sha256', 'evidence-'.$gate->value),
            );
        }

        $release = $lifecycle->transition(
            $release,
            CurriculumReleaseState::InReview,
            CurriculumReleaseState::Approved,
            $reviewer,
            'Every required gate has immutable evidence.',
        );
        $release = $lifecycle->transition(
            $release,
            CurriculumReleaseState::Approved,
            CurriculumReleaseState::Active,
            $reviewer,
            'Activate the fully reviewed release.',
        );

        $this->assertSame(CurriculumReleaseState::Active, $release->state);
        $this->assertFalse($release->preview_only);
        $this->assertTrue($release->package->is_active);
        $this->assertSame($reviewer->id, $release->activated_by_user_id);
        $this->assertDatabaseCount('curriculum_release_approvals', count(CurriculumApprovalGate::cases()));
        $this->assertDatabaseCount('curriculum_release_events', count(CurriculumApprovalGate::cases()) + 3);
        $firstApproval = CurriculumReleaseApproval::query()->orderBy('id')->firstOrFail();
        $this->assertSame($reviewer->id, $firstApproval->recorded_by_user_id);
        $this->assertSame('Named Reviewer 1', $firstApproval->reviewer_name);
        $this->assertSame($reviewer->id, $firstApproval->recorder->id);
    }

    public function test_even_fully_approved_draft_lifecycle_package_cannot_activate(): void
    {
        [$release, $reviewer] = $this->releaseFixture('draft');
        $lifecycle = app(CurriculumReleaseLifecycle::class);
        $release = $lifecycle->transition(
            $release,
            CurriculumReleaseState::Draft,
            CurriculumReleaseState::InReview,
            $reviewer,
            'Begin review of a draft package.',
        );
        foreach (CurriculumApprovalGate::cases() as $gate) {
            $lifecycle->approveGate(
                $release,
                $gate,
                $reviewer,
                'Named Reviewer',
                'Recorded qualification',
                hash('sha256', 'draft-'.$gate->value),
            );
        }
        $release = $lifecycle->transition(
            $release,
            CurriculumReleaseState::InReview,
            CurriculumReleaseState::Approved,
            $reviewer,
            'Gate evidence is complete but package lifecycle remains draft.',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('lifecycle-draft');

        $lifecycle->transition(
            $release,
            CurriculumReleaseState::Approved,
            CurriculumReleaseState::Active,
            $reviewer,
            'Attempt activation.',
        );
    }

    public function test_activating_a_second_reviewed_release_retires_the_first_under_the_global_lifecycle_lock(): void
    {
        [$first, $recorder] = $this->releaseFixture('published', 'first');
        [$second] = $this->releaseFixture('published', 'second');
        $lifecycle = app(CurriculumReleaseLifecycle::class);

        $first = $this->approveAndActivate($lifecycle, $first, $recorder, 'first');
        $second = $this->approveAndActivate($lifecycle, $second, $recorder, 'second');

        $this->assertSame(CurriculumReleaseState::Retired, $first->fresh()->state);
        $this->assertFalse($first->package->fresh()->is_active);
        $this->assertSame(CurriculumReleaseState::Active, $second->fresh()->state);
        $this->assertTrue($second->package->fresh()->is_active);
        $this->assertSame(1, CurriculumRelease::query()->where('state', CurriculumReleaseState::Active->value)->count());
        $this->assertSame(1, CurriculumPackage::query()->where('is_active', true)->count());
    }

    public function test_lifecycle_writes_revalidate_the_current_recorder_role_inside_the_transaction(): void
    {
        $lifecycle = app(CurriculumReleaseLifecycle::class);
        [$transitionRelease, $staleTransitionActor] = $this->releaseFixture('published', 'stale-transition');
        User::query()->whereKey($staleTransitionActor->id)->update(['role' => UserRole::Learner->value]);

        try {
            $lifecycle->transition(
                $transitionRelease,
                CurriculumReleaseState::Draft,
                CurriculumReleaseState::InReview,
                $staleTransitionActor,
                'A stale in-memory role must not authorize this transition.',
            );
            $this->fail('A stale Superadmin role authorized a release transition.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(CurriculumReleaseState::Draft, $transitionRelease->fresh()->state);

        [$approvalRelease, $staleApprovalRecorder] = $this->releaseFixture('published', 'stale-approval');
        $approvalRelease = $lifecycle->transition(
            $approvalRelease,
            CurriculumReleaseState::Draft,
            CurriculumReleaseState::InReview,
            $staleApprovalRecorder,
            'Open the release for approval evidence.',
        );
        User::query()->whereKey($staleApprovalRecorder->id)->update(['role' => UserRole::Learner->value]);

        try {
            $lifecycle->approveGate(
                $approvalRelease,
                CurriculumApprovalGate::Content,
                $staleApprovalRecorder,
                'Named Reviewer',
                'Recorded qualification',
                hash('sha256', 'stale-recorder-evidence'),
            );
            $this->fail('A stale Superadmin role recorded release approval evidence.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
        $this->assertDatabaseMissing('curriculum_release_approvals', [
            'curriculum_release_id' => $approvalRelease->id,
        ]);
    }

    public function test_database_constraints_prevent_accidental_deletion_of_release_evidence(): void
    {
        [$release, $recorder] = $this->releaseFixture();
        $lifecycle = app(CurriculumReleaseLifecycle::class);
        $release = $lifecycle->transition(
            $release,
            CurriculumReleaseState::Draft,
            CurriculumReleaseState::InReview,
            $recorder,
            'Begin formal review.',
        );
        $lifecycle->approveGate(
            $release,
            CurriculumApprovalGate::Content,
            $recorder,
            'External Content Reviewer',
            'Hospitality English qualification evidence',
            hash('sha256', 'content-review-evidence'),
        );

        try {
            DB::table('curriculum_releases')->where('id', $release->id)->delete();
            $this->fail('Release deletion unexpectedly bypassed immutable approval evidence.');
        } catch (QueryException) {
            $this->assertDatabaseHas('curriculum_release_approvals', [
                'curriculum_release_id' => $release->id,
                'reviewer_name' => 'External Content Reviewer',
            ]);
        }

        $this->expectException(QueryException::class);
        DB::table('curriculum_packages')->where('id', $release->curriculum_package_id)->delete();
    }

    /** @return array{CurriculumRelease, User} */
    private function releaseFixture(string $lifecycleStatus = 'published', string $suffix = 'fixture'): array
    {
        $sha256 = hash('sha256', 'release-source-'.$lifecycleStatus.'-'.$suffix);
        $package = CurriculumPackage::query()->create([
            'package_name' => 'hospitrainity-test-'.$suffix,
            'content_version' => '1.0.0-'.$lifecycleStatus.'-'.$suffix,
            'schema_version' => '2.1.0',
            'namespace_uuid' => '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
            'lifecycle_status' => $lifecycleStatus,
            'source_path' => 'test-fixture',
            'source_tree_sha256' => $sha256,
            'source_file_count' => 1,
            'source_byte_count' => 1,
            'counts' => [],
            'projection_meta' => [],
            'laravel_projection_sha256' => hash('sha256', 'laravel-'.$lifecycleStatus),
            'standalone_sha256' => hash('sha256', 'standalone-'.$lifecycleStatus),
            'is_active' => false,
            'imported_at' => now(),
        ]);
        $release = CurriculumRelease::query()->create([
            'curriculum_package_id' => $package->id,
            'state' => CurriculumReleaseState::Draft,
            'preview_only' => true,
            'source_tree_sha256' => $sha256,
        ]);
        $reviewer = User::factory()->create(['role' => UserRole::Superadmin]);

        return [$release, $reviewer];
    }

    private function approveAndActivate(
        CurriculumReleaseLifecycle $lifecycle,
        CurriculumRelease $release,
        User $recorder,
        string $suffix,
    ): CurriculumRelease {
        $release = $lifecycle->transition(
            $release,
            CurriculumReleaseState::Draft,
            CurriculumReleaseState::InReview,
            $recorder,
            'Begin '.$suffix.' formal review.',
        );
        foreach (CurriculumApprovalGate::cases() as $gate) {
            $lifecycle->approveGate(
                $release,
                $gate,
                $recorder,
                ucfirst($suffix).' '.$gate->value.' reviewer',
                'Recorded external qualification',
                hash('sha256', $suffix.'-'.$gate->value),
            );
        }
        $release = $lifecycle->transition(
            $release,
            CurriculumReleaseState::InReview,
            CurriculumReleaseState::Approved,
            $recorder,
            'Every '.$suffix.' gate has evidence.',
        );

        return $lifecycle->transition(
            $release,
            CurriculumReleaseState::Approved,
            CurriculumReleaseState::Active,
            $recorder,
            'Activate '.$suffix.' reviewed release.',
        );
    }
}
