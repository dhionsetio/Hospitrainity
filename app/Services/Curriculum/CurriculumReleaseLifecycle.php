<?php

namespace App\Services\Curriculum;

use App\Enums\CurriculumApprovalGate;
use App\Enums\CurriculumReleaseState;
use App\Models\CurriculumPackage;
use App\Models\CurriculumRelease;
use App\Models\CurriculumReleaseApproval;
use App\Models\CurriculumReleaseEvent;
use App\Models\User;
use App\Services\SearchIndexBuilder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CurriculumReleaseLifecycle
{
    /** @var array<string, list<CurriculumReleaseState>> */
    private const ALLOWED = [
        'draft' => [CurriculumReleaseState::InReview],
        'in_review' => [CurriculumReleaseState::Draft, CurriculumReleaseState::Approved],
        'approved' => [CurriculumReleaseState::InReview, CurriculumReleaseState::Active],
        'active' => [CurriculumReleaseState::Retired, CurriculumReleaseState::Withdrawn],
        'retired' => [CurriculumReleaseState::Active],
        'withdrawn' => [],
    ];

    public function __construct(
        private readonly CurriculumReleaseGuard $guard,
        private readonly SearchIndexBuilder $searchIndex,
    ) {}

    public function transition(
        CurriculumRelease $release,
        CurriculumReleaseState $expected,
        CurriculumReleaseState $next,
        User $actor,
        string $reason,
    ): CurriculumRelease {
        if (! $actor->isSuperAdmin()) {
            throw new AuthorizationException('Only a Superadmin may change curriculum release state.');
        }

        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw new RuntimeException('A release-state reason of 1–1000 characters is required.');
        }

        $updated = DB::transaction(function () use ($release, $expected, $next, $actor, $reason): CurriculumRelease {
            $this->lockLifecycle();
            $lockedActor = User::query()->lockForUpdate()->find($actor->getKey());
            if ($lockedActor === null || ! $lockedActor->isSuperAdmin()) {
                throw new AuthorizationException('Only a Superadmin may change curriculum release state.');
            }
            $locked = CurriculumRelease::query()->with('package')->lockForUpdate()->findOrFail($release->getKey());
            if ($locked->state !== $expected) {
                throw new RuntimeException('The curriculum release state changed since it was reviewed.');
            }

            if (! in_array($next, self::ALLOWED[$expected->value], true)) {
                throw new RuntimeException("The {$expected->value} to {$next->value} release transition is not allowed.");
            }

            if (in_array($next, [CurriculumReleaseState::Approved, CurriculumReleaseState::Active], true)) {
                $this->assertAllApprovalGates($locked);
            }

            if ($next === CurriculumReleaseState::Active) {
                $this->activate($locked, $lockedActor);
            } else {
                $locked->forceFill([
                    'state' => $next,
                    'preview_only' => true,
                    'retired_at' => in_array($next, [CurriculumReleaseState::Retired, CurriculumReleaseState::Withdrawn], true)
                        ? now()
                        : $locked->retired_at,
                ])->save();

                if ($expected === CurriculumReleaseState::Active) {
                    $locked->package()->update(['is_active' => false]);
                }
            }

            $this->recordEvent($locked, $lockedActor, 'release.transitioned', $expected, $next, $reason);

            return $locked->fresh(['package', 'approvals']);
        }, 3);

        if ($expected === CurriculumReleaseState::Active || $next === CurriculumReleaseState::Active) {
            $this->searchIndex->rebuild();
        }

        return $updated;
    }

    public function approveGate(
        CurriculumRelease $release,
        CurriculumApprovalGate $gate,
        User $recorder,
        string $reviewerName,
        string $qualification,
        string $evidenceSha256,
    ): CurriculumReleaseApproval {
        if (! $recorder->isSuperAdmin()) {
            throw new AuthorizationException('Only an authorized Superadmin may record release approval evidence.');
        }

        $reviewerName = trim($reviewerName);
        $qualification = trim($qualification);
        if ($reviewerName === ''
            || mb_strlen($reviewerName) > 255
            || $qualification === ''
            || mb_strlen($qualification) > 255
            || preg_match('/\A[0-9a-f]{64}\z/', $evidenceSha256) !== 1) {
            throw new RuntimeException('Named reviewer, qualification, and a lowercase SHA-256 evidence hash are required.');
        }

        return DB::transaction(function () use ($release, $gate, $recorder, $reviewerName, $qualification, $evidenceSha256): CurriculumReleaseApproval {
            $lockedRecorder = User::query()->lockForUpdate()->find($recorder->getKey());
            if ($lockedRecorder === null || ! $lockedRecorder->isSuperAdmin()) {
                throw new AuthorizationException('Only an authorized Superadmin may record release approval evidence.');
            }
            $locked = CurriculumRelease::query()->lockForUpdate()->findOrFail($release->getKey());
            if ($locked->state !== CurriculumReleaseState::InReview) {
                throw new RuntimeException('Approval evidence may be recorded only while the release is in review.');
            }

            if (CurriculumReleaseApproval::query()
                ->where('curriculum_release_id', $locked->getKey())
                ->where('gate', $gate->value)
                ->exists()) {
                throw new RuntimeException('This approval gate already has immutable evidence for the release.');
            }

            $approval = CurriculumReleaseApproval::query()->create([
                'curriculum_release_id' => $locked->getKey(),
                'gate' => $gate,
                // The authenticated Superadmin records the decision but is not
                // represented as the specialist unless the named evidence says so.
                'recorded_by_user_id' => $lockedRecorder->getKey(),
                'reviewer_name' => $reviewerName,
                'reviewer_qualification' => $qualification,
                'evidence_sha256' => $evidenceSha256,
                'approved_at' => now(),
            ]);
            $this->recordEvent(
                $locked,
                $lockedRecorder,
                'release.gate_approved',
                $locked->state,
                $locked->state,
                'Recorded immutable approval evidence.',
                ['gate' => $gate->value, 'approval_id' => $approval->getKey()],
            );

            return $approval;
        }, 3);
    }

    private function assertAllApprovalGates(CurriculumRelease $release): void
    {
        $approved = $release->approvals()->pluck('gate')->map(
            static fn ($gate): string => $gate instanceof CurriculumApprovalGate ? $gate->value : (string) $gate,
        )->all();
        $missing = array_values(array_diff(CurriculumApprovalGate::values(), $approved));
        if ($missing !== []) {
            throw new RuntimeException('Release approval gates are incomplete: '.implode(', ', $missing).'.');
        }

        if (! $this->guard->releaseHasCompleteApprovalEvidence($release)) {
            throw new RuntimeException('Release approval evidence is incomplete or invalid.');
        }

        if (! hash_equals($release->source_tree_sha256, (string) $release->package->source_tree_sha256)) {
            throw new RuntimeException('Release source checksum no longer matches its immutable package.');
        }
    }

    private function activate(CurriculumRelease $release, User $actor): void
    {
        if (strtolower(trim((string) $release->package->lifecycle_status)) === 'draft') {
            throw new RuntimeException('A lifecycle-draft package cannot become an active release.');
        }

        $activeReleases = CurriculumRelease::query()
            ->where('state', CurriculumReleaseState::Active->value)
            ->whereKeyNot($release->getKey())
            ->lockForUpdate()
            ->get();
        foreach ($activeReleases as $previous) {
            $previous->forceFill([
                'state' => CurriculumReleaseState::Retired,
                'preview_only' => true,
                'retired_at' => now(),
            ])->save();
            CurriculumPackage::query()->whereKey($previous->curriculum_package_id)->update(['is_active' => false]);
            $this->recordEvent(
                $previous,
                $actor,
                'release.retired_by_activation',
                CurriculumReleaseState::Active,
                CurriculumReleaseState::Retired,
                'A newly approved release was activated.',
                ['replacement_release_id' => $release->getKey()],
            );
        }

        CurriculumPackage::query()->where('is_active', true)->whereKeyNot($release->curriculum_package_id)->update(['is_active' => false]);
        $release->package()->update(['is_active' => true]);
        $release->forceFill([
            'state' => CurriculumReleaseState::Active,
            'preview_only' => false,
            'activated_by_user_id' => $actor->getKey(),
            'activated_at' => now(),
            'retired_at' => null,
        ])->save();
    }

    private function lockLifecycle(): void
    {
        $lock = DB::table('curriculum_release_locks')
            ->where('name', 'lifecycle-transition')
            ->lockForUpdate()
            ->first();
        if ($lock === null) {
            throw new RuntimeException('The curriculum release lifecycle lock is missing.');
        }
    }

    /** @param array<string, mixed>|null $metadata */
    private function recordEvent(
        CurriculumRelease $release,
        User $actor,
        string $event,
        CurriculumReleaseState $from,
        CurriculumReleaseState $to,
        string $reason,
        ?array $metadata = null,
    ): void {
        CurriculumReleaseEvent::query()->create([
            'curriculum_release_id' => $release->getKey(),
            'actor_user_id' => $actor->getKey(),
            'event' => $event,
            'from_state' => $from,
            'to_state' => $to,
            'reason' => $reason,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
