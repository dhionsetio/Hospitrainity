<?php

namespace App\Services\Curriculum;

use App\Enums\CurriculumDraftStatus;
use App\Enums\CurriculumImportStatus;
use App\Jobs\CompileCurriculumImport;
use App\Models\CurriculumDraft;
use App\Models\CurriculumDraftBlock;
use App\Models\CurriculumDraftEntity;
use App\Models\CurriculumImport;
use App\Models\CurriculumPackage;
use App\Models\User;
use App\Services\UploadSecurityService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class CurriculumImportWorkspace
{
    public function __construct(
        private readonly CurriculumUploadInspector $inspector,
        private readonly CanonicalPackageReader $reader,
        private readonly CurriculumDraftLifecycle $lifecycle,
        private readonly UploadSecurityService $uploadSecurity,
    ) {}

    public function queue(CurriculumDraft $draft, User $actor, int $expectedRevision, UploadedFile $source, string $purpose): CurriculumImport
    {
        $metadata = $this->inspector->docx($source);
        $scan = $this->uploadSecurity->inspect($source, $actor, 'docx_import', $metadata);
        $disk = Storage::disk((string) config('curriculum.import.disk'));
        $publicId = (string) Str::uuid();
        $sourcePath = trim((string) config('curriculum.import.quarantine_prefix'), '/')."/{$publicId}.docx";
        $work = trim((string) config('curriculum.import.work_prefix'), '/')."/{$publicId}";
        $stored = $disk->putFileAs(dirname($sourcePath), $source, basename($sourcePath));
        if ($stored !== $sourcePath || ! $disk->exists($sourcePath)) {
            throw new RuntimeException('The DOCX could not be placed in private quarantine.');
        }

        try {
            $import = DB::transaction(function () use ($draft, $actor, $expectedRevision, $metadata, $purpose, $publicId, $sourcePath, $work): CurriculumImport {
                $locked = CurriculumDraft::query()->lockForUpdate()->findOrFail($draft->id);
                $this->lifecycle->assertRevision($locked, $expectedRevision);
                if ($locked->status !== CurriculumDraftStatus::Draft) {
                    throw new RuntimeException('DOCX imports are allowed only in a draft-status workspace.');
                }
                if (CurriculumImport::query()->where('curriculum_draft_id', $locked->id)
                    ->whereIn('status', [CurriculumImportStatus::Queued, CurriculumImportStatus::Processing])->exists()) {
                    throw new RuntimeException('This workspace already has a queued or processing import.');
                }
                $base = $locked->basePackage ?? CurriculumPackage::active();
                if ($base === null) {
                    throw new RuntimeException('A verified canonical base package is required before DOCX compilation.');
                }
                $import = new CurriculumImport;
                $import->public_id = $publicId;
                $import->fill([
                    'curriculum_draft_id' => $locked->id,
                    'base_package_id' => $base->id,
                    'status' => CurriculumImportStatus::Queued,
                    'revision' => 1,
                    'source_original_name' => $metadata['original_name'],
                    'source_storage_path' => $sourcePath,
                    'source_sha256' => $metadata['sha256'],
                    'source_bytes' => $metadata['bytes'],
                    'detected_mime' => $metadata['mime'],
                    'declared_purpose' => trim($purpose),
                    'compiled_package_path' => "{$work}/package",
                    'evidence_path' => "{$work}/evidence.json",
                    'created_by' => $actor->id,
                ]);
                $import->save();
                $this->lifecycle->record($locked, $actor, 'docx_import_queued', metadata: [
                    'import_id' => $publicId,
                    'source_sha256' => $metadata['sha256'],
                    'source_bytes' => $metadata['bytes'],
                ]);

                return $import;
            }, attempts: 3);
        } catch (\Throwable $exception) {
            $disk->delete($sourcePath);
            throw $exception;
        }

        CompileCurriculumImport::dispatch($import);
        $this->uploadSecurity->markPromoted($scan);

        return $import;
    }

    public function accept(CurriculumDraft $draft, CurriculumImport $import, User $actor, int $draftRevision, int $importRevision): CurriculumDraft
    {
        return DB::transaction(function () use ($draft, $import, $actor, $draftRevision, $importRevision): CurriculumDraft {
            $lockedDraft = CurriculumDraft::query()->lockForUpdate()->findOrFail($draft->id);
            $this->lifecycle->assertRevision($lockedDraft, $draftRevision);
            if ($lockedDraft->status !== CurriculumDraftStatus::Draft) {
                throw new RuntimeException('A compiled import can only be accepted into a draft-status workspace.');
            }
            $lockedImport = CurriculumImport::query()->lockForUpdate()->with('basePackage')->findOrFail($import->id);
            if ($lockedImport->curriculum_draft_id !== $lockedDraft->id) {
                throw new RuntimeException('The import does not belong to this draft workspace.');
            }
            if ($lockedImport->revision !== $importRevision || $lockedImport->status !== CurriculumImportStatus::Ready) {
                throw new RuntimeException('The import is no longer ready at the reviewed revision.');
            }
            $disk = Storage::disk((string) config('curriculum.import.disk'));
            $root = $disk->path((string) $lockedImport->compiled_package_path);
            $evidence = $disk->path((string) $lockedImport->evidence_path);
            $package = $this->reader->read($root, $evidence);
            $baseEntities = $lockedImport->basePackage->entities()->pluck('id', 'source_path');

            CurriculumDraftBlock::query()->where('curriculum_draft_id', $lockedDraft->id)->delete();
            CurriculumDraftEntity::query()->where('curriculum_draft_id', $lockedDraft->id)->delete();
            foreach ($package->entities as $source) {
                $payload = $source['payload'];
                $blocks = $source['entity_type'] === 'lesson-section' ? array_values($payload['blocks'] ?? []) : [];
                unset($payload['blocks']);
                $entity = CurriculumDraftEntity::create([
                    'curriculum_draft_id' => $lockedDraft->id,
                    'source_entity_id' => $baseEntities[$source['source_path']] ?? null,
                    'entity_uuid' => $source['entity_uuid'],
                    'code' => $source['code'],
                    'entity_type' => $source['entity_type'],
                    'parent_code' => $source['parent_code'],
                    'position' => $source['position'],
                    'source_path' => $source['source_path'],
                    'payload' => $payload,
                    'revision' => 1,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
                foreach ($blocks as $index => $block) {
                    CurriculumDraftBlock::create([
                        'curriculum_draft_id' => $lockedDraft->id,
                        'curriculum_draft_entity_id' => $entity->id,
                        'block_uuid' => $block['id'],
                        'block_type' => $block['type'],
                        'position' => $index + 1,
                        'payload' => $block,
                        'revision' => 1,
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ]);
                }
            }
            $lockedDraft->forceFill([
                'base_package_id' => $lockedDraft->base_package_id ?? $lockedImport->base_package_id,
                'content_version' => $package->metadata['content_version'],
                'schema_version' => $package->metadata['schema_version'],
                'namespace_uuid' => $package->metadata['namespace'],
                'revision' => $lockedDraft->revision + 1,
                'validation_report' => null,
                'diff_report' => null,
                'validated_by' => null,
                'validated_at' => null,
                'updated_by' => $actor->id,
            ])->save();
            $lockedImport->forceFill([
                'status' => CurriculumImportStatus::Accepted,
                'revision' => $lockedImport->revision + 1,
                'accepted_by' => $actor->id,
                'accepted_at' => now(),
            ])->save();
            $this->lifecycle->record($lockedDraft, $actor, 'docx_import_accepted', metadata: [
                'import_id' => $lockedImport->public_id,
                'source_sha256' => $lockedImport->source_sha256,
                'compiled_tree_sha256' => $package->treeSha256,
                'diff_summary' => $lockedImport->diff_report['summary'] ?? null,
            ]);

            return $lockedDraft->fresh();
        }, attempts: 3);
    }
}
