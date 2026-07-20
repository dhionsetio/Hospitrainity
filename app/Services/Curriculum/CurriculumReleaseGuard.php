<?php

namespace App\Services\Curriculum;

use App\Enums\CurriculumApprovalGate;
use App\Enums\CurriculumReleaseState;
use App\Models\CurriculumPackage;
use App\Models\CurriculumRelease;
use RuntimeException;

final class CurriculumReleaseGuard
{
    public function assertImportMayActivate(CanonicalPackage $source): void
    {
        $production = app()->isProduction();
        if ($production && strtolower(trim((string) $source->metadata['status'])) === 'draft') {
            throw new RuntimeException('Production import refused: a lifecycle-draft curriculum package cannot become active.');
        }

        $activePackages = CurriculumPackage::query()
            ->with('release')
            ->where('is_active', true)
            ->limit(2)
            ->get();
        if ($activePackages->count() > 1) {
            throw new RuntimeException('Release containment refused the import because multiple curriculum packages are marked active.');
        }

        $active = $activePackages->first();
        if ($active === null) {
            if ($production) {
                throw new RuntimeException('Production import refused: an initial curriculum package must be staged and pass every release gate before activation.');
            }

            return;
        }

        if (hash_equals((string) $active->source_tree_sha256, $source->treeSha256)) {
            if ($production) {
                $this->assertDeliverable($active);
            }

            return;
        }

        if (app()->environment('testing')
            && config('curriculum.release.allow_unapproved_replacement_for_tests') === true) {
            return;
        }

        throw new RuntimeException(
            'Release containment refused the import: a replacement package cannot become active before the required human approval gates close.',
        );
    }

    public function recordImportedPackage(CurriculumPackage $package): CurriculumRelease
    {
        $release = CurriculumRelease::query()->firstOrCreate(
            ['curriculum_package_id' => $package->getKey()],
            [
                'state' => CurriculumReleaseState::Draft,
                'preview_only' => true,
                'source_tree_sha256' => $package->source_tree_sha256,
            ],
        );
        if (! hash_equals((string) $package->source_tree_sha256, (string) $release->source_tree_sha256)) {
            throw new RuntimeException('Release containment refused a package whose release checksum does not match.');
        }

        return $release;
    }

    public function assertDeliverable(CurriculumPackage $package): void
    {
        $draft = strtolower(trim((string) $package->lifecycle_status)) === 'draft';
        $release = $package->release;
        if ($release !== null
            && ! hash_equals((string) $package->source_tree_sha256, (string) $release->source_tree_sha256)) {
            throw new RuntimeException('Curriculum delivery refused: release and package source checksums do not match.');
        }

        if (app()->isProduction()) {
            $activeReleaseCount = CurriculumRelease::query()
                ->where('state', CurriculumReleaseState::Active->value)
                ->limit(2)
                ->count();
            if ($draft
                || $release === null
                || $release->state !== CurriculumReleaseState::Active
                || $activeReleaseCount !== 1
                || $release->preview_only
                || ! $this->releaseHasCompleteApprovalEvidence($release)
                || ! $this->releaseHasActivationEvidence($release)
                || ! hash_equals((string) $package->source_tree_sha256, (string) $release->source_tree_sha256)) {
                throw new RuntimeException('Production delivery refused: the active curriculum does not have an approved non-draft release.');
            }

            return;
        }

        if ($draft && config('curriculum.release.allow_draft_active_preview') !== true) {
            throw new RuntimeException('Draft curriculum preview is disabled in this environment.');
        }
    }

    public function releaseHasCompleteApprovalEvidence(CurriculumRelease $release): bool
    {
        $rows = $release->approvals()->get()->map(
            static fn ($approval): array => [
                'gate' => $approval->gate instanceof CurriculumApprovalGate
                    ? $approval->gate->value
                    : (string) $approval->gate,
                'recorded_by_user_id' => $approval->recorded_by_user_id,
                'reviewer_name' => $approval->reviewer_name,
                'reviewer_qualification' => $approval->reviewer_qualification,
                'evidence_sha256' => $approval->evidence_sha256,
                'approved_at' => $approval->approved_at,
            ],
        )->all();

        return $this->approvalRowsAreComplete($rows);
    }

    private function releaseHasActivationEvidence(CurriculumRelease $release): bool
    {
        if ($release->activated_by_user_id === null || $release->activated_at === null) {
            return false;
        }

        return $release->events()
            ->where('to_state', CurriculumReleaseState::Active->value)
            ->whereIn('from_state', [
                CurriculumReleaseState::Approved->value,
                CurriculumReleaseState::Retired->value,
            ])
            ->where('actor_user_id', $release->activated_by_user_id)
            ->whereNotNull('created_at')
            ->where('reason', '!=', '')
            ->exists();
    }

    /** @param array<string, mixed> $snapshot */
    public function assertRollbackSnapshotMayRestore(array $snapshot): void
    {
        if (! app()->isProduction()) {
            return;
        }

        $tables = $snapshot['tables'] ?? [];
        $packages = is_array($tables['curriculum_packages'] ?? null)
            ? $tables['curriculum_packages']
            : [];
        $releases = is_array($tables['curriculum_releases'] ?? null)
            ? $tables['curriculum_releases']
            : [];
        $approvals = is_array($tables['curriculum_release_approvals'] ?? null)
            ? $tables['curriculum_release_approvals']
            : [];
        $events = is_array($tables['curriculum_release_events'] ?? null)
            ? $tables['curriculum_release_events']
            : [];
        $activePackages = array_values(array_filter(
            $packages,
            static fn (mixed $row): bool => is_array($row) && (bool) ($row['is_active'] ?? false),
        ));
        $activeReleases = array_values(array_filter(
            $releases,
            static fn (mixed $row): bool => is_array($row)
                && ($row['state'] ?? null) === CurriculumReleaseState::Active->value,
        ));
        if (count($activePackages) !== 1 || count($activeReleases) !== 1) {
            $this->refuseProductionRollback();
        }

        $package = $activePackages[0];
        $release = $activeReleases[0];
        $releaseApprovals = array_values(array_filter(
            $approvals,
            static fn (mixed $row): bool => is_array($row)
                && (string) ($row['curriculum_release_id'] ?? '') === (string) ($release['id'] ?? ''),
        ));
        $activationEvents = array_values(array_filter(
            $events,
            static fn (mixed $row): bool => is_array($row)
                && (string) ($row['curriculum_release_id'] ?? '') === (string) ($release['id'] ?? '')
                && ($row['to_state'] ?? null) === CurriculumReleaseState::Active->value
                && in_array(
                    $row['from_state'] ?? null,
                    [CurriculumReleaseState::Approved->value, CurriculumReleaseState::Retired->value],
                    true,
                )
                && ($row['actor_user_id'] ?? null) !== null
                && (string) ($row['actor_user_id'] ?? '') === (string) ($release['activated_by_user_id'] ?? '')
                && trim((string) ($row['reason'] ?? '')) !== ''
                && ($row['created_at'] ?? null) !== null,
        ));
        if (strtolower(trim((string) ($package['lifecycle_status'] ?? ''))) === 'draft'
            || (bool) ($release['preview_only'] ?? true)
            || ($release['activated_by_user_id'] ?? null) === null
            || ($release['activated_at'] ?? null) === null
            || $activationEvents === []
            || (string) ($release['curriculum_package_id'] ?? '') !== (string) ($package['id'] ?? '')
            || ! hash_equals(
                (string) ($package['source_tree_sha256'] ?? ''),
                (string) ($release['source_tree_sha256'] ?? ''),
            )
            || ! $this->approvalRowsAreComplete($releaseApprovals)) {
            $this->refuseProductionRollback();
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function approvalRowsAreComplete(array $rows): bool
    {
        if (count($rows) !== count(CurriculumApprovalGate::cases())) {
            return false;
        }

        $gates = [];
        foreach ($rows as $row) {
            $gate = (string) ($row['gate'] ?? '');
            if (! in_array($gate, CurriculumApprovalGate::values(), true)
                || ($row['recorded_by_user_id'] ?? null) === null
                || trim((string) ($row['reviewer_name'] ?? '')) === ''
                || trim((string) ($row['reviewer_qualification'] ?? '')) === ''
                || preg_match('/\A[0-9a-f]{64}\z/', (string) ($row['evidence_sha256'] ?? '')) !== 1
                || ($row['approved_at'] ?? null) === null) {
                return false;
            }
            $gates[$gate] = true;
        }

        return count($gates) === count(CurriculumApprovalGate::cases());
    }

    private function refuseProductionRollback(): never
    {
        throw new RuntimeException('Production rollback refused: the snapshot does not contain exactly one fully approved, non-draft active release.');
    }
}
