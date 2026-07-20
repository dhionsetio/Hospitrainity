<?php

namespace App\Services\Curriculum;

use App\Enums\CurriculumDraftStatus;
use App\Models\CurriculumAsset;
use App\Models\CurriculumAssetBlob;
use App\Models\CurriculumDraft;
use App\Models\User;
use App\Services\UploadSecurityService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class CurriculumAssetWorkspace
{
    public function __construct(
        private readonly CurriculumUploadInspector $inspector,
        private readonly CurriculumDraftLifecycle $lifecycle,
        private readonly UploadSecurityService $uploadSecurity,
    ) {}

    /** @param array<string, mixed> $data */
    public function store(CurriculumDraft $draft, User $actor, int $expectedRevision, UploadedFile $file, array $data): CurriculumAsset
    {
        $metadata = $this->inspector->asset($file);
        $scan = $this->uploadSecurity->inspect($file, $actor, 'asset', $metadata);
        $disk = Storage::disk((string) config('curriculum.import.disk'));
        $uploadId = (string) Str::uuid();
        $quarantine = trim((string) config('curriculum.import.quarantine_prefix'), '/')."/assets/{$uploadId}.{$metadata['extension']}";
        $stored = $disk->putFileAs(dirname($quarantine), $file, basename($quarantine));
        if ($stored !== $quarantine || ! $disk->exists($quarantine)) {
            throw new RuntimeException('The asset could not be placed in private quarantine.');
        }
        $blobPath = trim((string) config('curriculum.import.blob_prefix'), '/').'/'.substr($metadata['sha256'], 0, 2)."/{$metadata['sha256']}.{$metadata['extension']}";
        try {
            if ($disk->exists($blobPath)) {
                $existingHash = hash_file('sha256', $disk->path($blobPath));
                if (! is_string($existingHash) || ! hash_equals($metadata['sha256'], $existingHash)) {
                    throw new RuntimeException('A digest-addressed asset failed integrity verification.');
                }
                $disk->delete($quarantine);
            } elseif (! $disk->move($quarantine, $blobPath)) {
                throw new RuntimeException('The validated asset could not be promoted from quarantine.');
            }

            $asset = DB::transaction(function () use ($draft, $actor, $expectedRevision, $metadata, $blobPath, $data): CurriculumAsset {
                $locked = CurriculumDraft::query()->lockForUpdate()->findOrFail($draft->id);
                $this->lifecycle->assertRevision($locked, $expectedRevision);
                if ($locked->status !== CurriculumDraftStatus::Draft) {
                    throw new RuntimeException('Assets can only be added to a draft-status workspace.');
                }
                $blob = CurriculumAssetBlob::query()->firstOrCreate(
                    ['sha256' => $metadata['sha256']],
                    [
                        'storage_path' => $blobPath,
                        'detected_mime' => $metadata['mime'],
                        'extension' => $metadata['extension'],
                        'bytes' => $metadata['bytes'],
                    ],
                );
                if ($blob->storage_path !== $blobPath || $blob->bytes !== $metadata['bytes'] || $blob->detected_mime !== $metadata['mime']) {
                    throw new RuntimeException('The existing digest record does not match the validated asset.');
                }
                $asset = new CurriculumAsset;
                $asset->public_id = (string) Str::uuid();
                $asset->fill([
                    'curriculum_draft_id' => $locked->id,
                    'curriculum_asset_blob_id' => $blob->id,
                    'display_name' => trim((string) $data['display_name']),
                    'kind' => $metadata['kind'],
                    'accessibility_text' => trim((string) $data['accessibility_text']),
                    'rights_basis' => trim((string) $data['rights_basis']),
                    'source_url' => isset($data['source_url']) && trim((string) $data['source_url']) !== '' ? trim((string) $data['source_url']) : null,
                    'created_by' => $actor->id,
                ]);
                $asset->save();
                $this->lifecycle->record($locked, $actor, 'asset_uploaded', metadata: [
                    'asset_id' => $asset->public_id,
                    'sha256' => $blob->sha256,
                    'bytes' => $blob->bytes,
                    'kind' => $asset->kind->value,
                    'deduplicated' => ! $blob->wasRecentlyCreated,
                ]);

                return $asset->load('blob');
            }, attempts: 3);
            $this->uploadSecurity->markPromoted($scan);

            return $asset;
        } catch (\Throwable $exception) {
            if ($disk->exists($quarantine)) {
                $disk->delete($quarantine);
            }
            throw $exception;
        }
    }
}
