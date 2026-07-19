<?php

namespace App\Services\Curriculum;

use App\Enums\CurriculumDraftStatus;
use App\Models\CurriculumDraft;
use App\Models\CurriculumImportRun;
use App\Models\CurriculumPackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class CurriculumDraftReview
{
    public function __construct(
        private readonly CurriculumDraftLifecycle $lifecycle,
        private readonly CurriculumDraftDiff $diff,
        private readonly CurriculumDraftPackageBuilder $builder,
        private readonly CanonicalCurriculumImporter $importer,
    ) {}

    public function validate(CurriculumDraft $draft, User $actor, int $expectedRevision): CurriculumDraft
    {
        $validating = $this->lifecycle->transition(
            $draft, CurriculumDraftStatus::Validating, $actor, $expectedRevision,
            'validation_started',
        );
        $diff = $this->diff->report($validating);

        try {
            $built = $this->builder->build($validating);
            $report = [
                'status' => 'valid',
                'validated_revision' => $validating->revision,
                'validated_at' => now()->toIso8601String(),
                'source_tree_sha256' => $built['package']->treeSha256,
                'counts' => $built['package']->counts,
                'package_path' => $built['root'],
                'evidence_path' => $built['evidencePath'],
                'errors' => [],
            ];

            return $this->lifecycle->transition(
                $validating, CurriculumDraftStatus::InReview, $actor, $validating->revision,
                'validation_passed', attributes: [
                    'validation_report' => $report,
                    'diff_report' => $diff,
                    'validated_by' => $actor->id,
                    'validated_at' => now(),
                    'submitted_by' => $actor->id,
                    'submitted_at' => now(),
                ], metadata: ['source_tree_sha256' => $built['package']->treeSha256],
            );
        } catch (Throwable $exception) {
            $report = [
                'status' => 'invalid',
                'validated_revision' => $validating->revision,
                'validated_at' => now()->toIso8601String(),
                'errors' => [['type' => $exception::class, 'message' => $exception->getMessage()]],
            ];
            $restored = $this->lifecycle->transition(
                $validating, CurriculumDraftStatus::Draft, $actor, $validating->revision,
                'validation_failed', attributes: ['validation_report' => $report, 'diff_report' => $diff],
            );

            return $restored;
        }
    }

    public function approve(CurriculumDraft $draft, User $actor, int $expectedRevision, string $reason): CurriculumDraft
    {
        if (($draft->validation_report['status'] ?? null) !== 'valid') {
            throw new RuntimeException('A passing validation report is required before approval.');
        }

        return $this->lifecycle->transition(
            $draft, CurriculumDraftStatus::Approved, $actor, $expectedRevision,
            'draft_approved', $reason, ['approved_by' => $actor->id, 'approved_at' => now()],
        );
    }

    public function requestChanges(CurriculumDraft $draft, User $actor, int $expectedRevision, string $reason): CurriculumDraft
    {
        return $this->lifecycle->transition(
            $draft, CurriculumDraftStatus::Draft, $actor, $expectedRevision,
            'changes_requested', $reason, [
                'validation_report' => null, 'diff_report' => null,
                'validated_by' => null, 'validated_at' => null,
                'submitted_by' => null, 'submitted_at' => null,
                'approved_by' => null, 'approved_at' => null,
            ],
        );
    }

    /** @return array{draft: CurriculumDraft, import: array<string, mixed>} */
    public function publish(CurriculumDraft $draft, User $actor, int $expectedRevision): array
    {
        return DB::transaction(function () use ($draft, $actor, $expectedRevision): array {
            $locked = CurriculumDraft::query()->lockForUpdate()->findOrFail($draft->id);
            $this->lifecycle->assertRevision($locked, $expectedRevision);
            if ($locked->status !== CurriculumDraftStatus::Approved) {
                throw new RuntimeException('Only an approved draft can be published.');
            }
            $built = $this->builder->build($locked, immutable: true);
            try {
                $report = $this->importer->import($built['package']);
            } catch (Throwable $exception) {
                $this->builder->discardImmutable($locked);
                throw $exception;
            }
            if (($report['status'] ?? null) !== 'imported') {
                throw new RuntimeException('Publication did not create a new immutable canonical version.');
            }
            $package = CurriculumPackage::active();
            if ($package === null || $package->content_version !== $locked->content_version) {
                throw new RuntimeException('The new canonical version was not activated.');
            }

            $published = $this->lifecycle->transition(
                $locked, CurriculumDraftStatus::Published, $actor, $locked->revision,
                'draft_published', attributes: [
                    'published_package_id' => $package->id,
                    'publication_run_id' => $report['run_id'],
                    'published_by' => $actor->id,
                    'published_at' => now(),
                ], metadata: [
                    'package_id' => $package->id,
                    'source_tree_sha256' => $package->source_tree_sha256,
                    'previous_content_version' => $locked->basePackage?->content_version,
                ],
            );

            return ['draft' => $published, 'import' => $report];
        }, attempts: 3);
    }

    /** @return array<string, mixed> */
    public function rollback(CurriculumDraft $draft, User $actor, int $expectedRevision): array
    {
        $draft->refresh();
        $this->lifecycle->assertRevision($draft, $expectedRevision);
        if ($draft->status !== CurriculumDraftStatus::Published || $draft->publication_run_id === null) {
            throw new RuntimeException('Only a recorded published draft can be rolled back.');
        }
        $active = CurriculumPackage::active();
        if ($active?->id !== $draft->published_package_id) {
            throw new RuntimeException('This publication is not the active canonical version and cannot be rolled back over a newer release.');
        }
        $run = CurriculumImportRun::query()->findOrFail($draft->publication_run_id);
        if ($run->rollback_path === null) {
            throw new RuntimeException('The publication has no recorded rollback artifact.');
        }
        $report = $this->importer->rollback($run->rollback_path);
        $draft->refresh();
        $draft->forceFill(['revision' => $draft->revision + 1, 'updated_by' => $actor->id])->save();
        $this->lifecycle->record($draft, $actor, 'publication_rolled_back', metadata: [
            'publication_run_id' => $run->id,
            'rollback_run_id' => $report['run_id'] ?? null,
            'restored_content_version' => $report['active_package']['content_version'] ?? null,
        ]);

        return $report;
    }
}
