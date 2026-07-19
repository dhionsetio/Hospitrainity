<?php

namespace App\Services\Curriculum;

use App\Enums\CurriculumBlockType;
use App\Enums\CurriculumDraftEntityType;
use App\Enums\CurriculumDraftStatus;
use App\Exceptions\CurriculumDraftConflictException;
use App\Models\CurriculumAsset;
use App\Models\CurriculumDraft;
use App\Models\CurriculumDraftBlock;
use App\Models\CurriculumDraftEntity;
use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class CurriculumDraftWorkspace
{
    public function __construct(private readonly CurriculumDraftLifecycle $lifecycle) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): CurriculumDraft
    {
        return DB::transaction(function () use ($actor, $data): CurriculumDraft {
            $base = ($data['source'] ?? 'clone') === 'clone'
                ? CurriculumPackage::query()->lockForUpdate()->where('is_active', true)->first()
                : null;
            if (($data['source'] ?? 'clone') === 'clone' && $base === null) {
                throw new RuntimeException('An active canonical package is required to clone a draft.');
            }

            $draft = CurriculumDraft::create([
                'base_package_id' => $base?->id,
                'package_name' => $base?->package_name ?? 'hospitrainity',
                'content_version' => $data['content_version'],
                'schema_version' => (string) config('curriculum.authoring_schema_version'),
                'namespace_uuid' => $base?->namespace_uuid ?? (string) Str::uuid(),
                'title' => $data['title'],
                'status' => CurriculumDraftStatus::Draft,
                'revision' => 1,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            if ($base !== null) {
                $this->clonePackage($draft, $base, $actor);
            }

            $this->lifecycle->record($draft, $actor, $base === null ? 'draft_created_empty' : 'draft_cloned', metadata: [
                'base_package_id' => $base?->id,
                'base_content_version' => $base?->content_version,
            ]);

            return $draft->fresh();
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    public function createEntity(CurriculumDraft $draft, User $actor, int $expectedDraftRevision, array $data): CurriculumDraftEntity
    {
        return DB::transaction(function () use ($draft, $actor, $expectedDraftRevision, $data): CurriculumDraftEntity {
            $lockedDraft = $this->editableDraft($draft, $expectedDraftRevision);
            $type = CurriculumDraftEntityType::from($data['entity_type']);
            $code = strtoupper(trim($data['code']));
            $position = (int) $data['position'];
            $payload = $this->newEntityPayload($lockedDraft, $type, $code, $data);

            $entity = CurriculumDraftEntity::create([
                'curriculum_draft_id' => $lockedDraft->id,
                'entity_uuid' => (string) Str::uuid(),
                'code' => $code,
                'entity_type' => $type->value,
                'parent_code' => $type === CurriculumDraftEntityType::Section ? $data['parent_code'] : null,
                'position' => $position,
                'source_path' => $this->newSourcePath($type, $code, (string) ($data['parent_code'] ?? '')),
                'payload' => $payload,
                'revision' => 1,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->persistEntityOrder($this->orderedEntities($lockedDraft, $entity, $position), $actor);

            $this->touchDraft($lockedDraft, $actor, 'entity_created', ['entity_id' => $entity->id, 'code' => $code, 'entity_type' => $type->value]);

            return $entity->fresh();
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    public function updateEntity(
        CurriculumDraft $draft,
        CurriculumDraftEntity $entity,
        User $actor,
        int $expectedDraftRevision,
        int $expectedEntityRevision,
        array $data,
    ): CurriculumDraftEntity {
        return DB::transaction(function () use ($draft, $entity, $actor, $expectedDraftRevision, $expectedEntityRevision, $data): CurriculumDraftEntity {
            $lockedDraft = $this->editableDraft($draft, $expectedDraftRevision);
            $locked = $this->lockedEntity($lockedDraft, $entity, $expectedEntityRevision);
            if ($locked->archived_at !== null) {
                throw new RuntimeException('An archived draft entity must be restored before it can be edited.');
            }
            $previousParentCode = $locked->parent_code;
            $payload = $locked->payload;

            if ($locked->entity_type === CurriculumDraftEntityType::Chapter->value) {
                $payload['title'] = $data['title'];
                $payload['module'] = (int) $data['position'];
            } elseif ($locked->entity_type === CurriculumDraftEntityType::Section->value) {
                $payload['title'] = $data['title'];
                $payload['chapter_code'] = $data['parent_code'];
                $payload['order'] = (int) $data['position'];
                $locked->parent_code = $data['parent_code'];
                $locked->source_path = $this->newSourcePath(CurriculumDraftEntityType::Section, (string) $locked->code, $data['parent_code']);
            } elseif ($locked->entity_type === CurriculumDraftEntityType::Outcome->value) {
                $payload['module'] = (int) $data['module'];
                $payload['statement'] = $data['statement'];
                $payload['type'] = $data['outcome_type'];
                $payload['provisional_band'] = $data['provisional_band'];
            } else {
                throw new RuntimeException('This entity type is not editable in ADM-2.');
            }

            $locked->payload = $payload;
            $locked->updated_by = $actor->id;
            if ($locked->entity_type === CurriculumDraftEntityType::Section->value && $previousParentCode !== $locked->parent_code) {
                $this->persistEntityOrder(
                    $this->availableEntitySiblings($lockedDraft, $locked->entity_type, $previousParentCode, $locked->id),
                    $actor,
                );
            }
            $this->persistEntityOrder($this->orderedEntities($lockedDraft, $locked, (int) $data['position']), $actor);
            $this->touchDraft($lockedDraft, $actor, 'entity_updated', ['entity_id' => $locked->id, 'code' => $locked->code]);

            return $locked->fresh();
        }, attempts: 3);
    }

    public function moveEntity(
        CurriculumDraft $draft,
        CurriculumDraftEntity $entity,
        User $actor,
        int $expectedDraftRevision,
        int $expectedEntityRevision,
        int $newPosition,
    ): void {
        DB::transaction(function () use ($draft, $entity, $actor, $expectedDraftRevision, $expectedEntityRevision, $newPosition): void {
            $lockedDraft = $this->editableDraft($draft, $expectedDraftRevision);
            $locked = $this->lockedEntity($lockedDraft, $entity, $expectedEntityRevision);
            if ($locked->archived_at !== null) {
                throw new RuntimeException('An archived draft entity must be restored before it can be reordered.');
            }
            $this->persistEntityOrder($this->orderedEntities($lockedDraft, $locked, $newPosition), $actor);
            $this->touchDraft($lockedDraft, $actor, 'entity_reordered', ['entity_id' => $locked->id, 'position' => $newPosition]);
        }, attempts: 3);
    }

    public function archiveEntity(CurriculumDraft $draft, CurriculumDraftEntity $entity, User $actor, int $expectedDraftRevision, int $expectedEntityRevision): void
    {
        DB::transaction(function () use ($draft, $entity, $actor, $expectedDraftRevision, $expectedEntityRevision): void {
            $lockedDraft = $this->editableDraft($draft, $expectedDraftRevision);
            $locked = $this->lockedEntity($lockedDraft, $entity, $expectedEntityRevision);
            $ids = $this->descendantIds($lockedDraft, $locked);
            CurriculumDraftEntity::query()->whereIn('id', $ids)->update([
                'archived_at' => now(), 'updated_by' => $actor->id, 'updated_at' => now(),
                'revision' => DB::raw('revision + 1'),
            ]);
            $this->persistEntityOrder(
                $this->availableEntitySiblings($lockedDraft, $locked->entity_type, $locked->parent_code, $locked->id),
                $actor,
            );
            $this->touchDraft($lockedDraft, $actor, 'entity_archived', ['entity_id' => $locked->id, 'affected_entities' => count($ids)]);
        }, attempts: 3);
    }

    public function restoreEntity(CurriculumDraft $draft, CurriculumDraftEntity $entity, User $actor, int $expectedDraftRevision, int $expectedEntityRevision): void
    {
        DB::transaction(function () use ($draft, $entity, $actor, $expectedDraftRevision, $expectedEntityRevision): void {
            $lockedDraft = $this->editableDraft($draft, $expectedDraftRevision);
            $locked = $this->lockedEntity($lockedDraft, $entity, $expectedEntityRevision);
            if ($locked->archived_at === null) {
                throw new RuntimeException('This draft entity is already available.');
            }
            if ($locked->entity_type === CurriculumDraftEntityType::Section->value && ! CurriculumDraftEntity::query()->lockForUpdate()
                ->where('curriculum_draft_id', $lockedDraft->id)
                ->where('entity_type', CurriculumDraftEntityType::Chapter->value)
                ->where('code', $locked->parent_code)
                ->whereNull('archived_at')->exists()) {
                throw new RuntimeException('Restore the parent module before restoring this lesson section.');
            }
            $restorePosition = max(1, (int) $locked->position);
            $locked->forceFill(['archived_at' => null, 'updated_by' => $actor->id]);
            $this->persistEntityOrder($this->orderedEntities($lockedDraft, $locked, $restorePosition), $actor);
            $this->touchDraft($lockedDraft, $actor, 'entity_restored', ['entity_id' => $locked->id]);
        }, attempts: 3);
    }

    /** @param array<string, mixed> $payload */
    public function createBlock(
        CurriculumDraft $draft,
        CurriculumDraftEntity $section,
        User $actor,
        int $expectedDraftRevision,
        int $expectedEntityRevision,
        CurriculumBlockType $type,
        array $payload,
        ?CurriculumAsset $asset = null,
    ): CurriculumDraftBlock {
        return DB::transaction(function () use ($draft, $section, $actor, $expectedDraftRevision, $expectedEntityRevision, $type, $payload, $asset): CurriculumDraftBlock {
            $lockedDraft = $this->editableDraft($draft, $expectedDraftRevision);
            $lockedSection = $this->lockedEntity($lockedDraft, $section, $expectedEntityRevision);
            if ($lockedSection->entity_type !== CurriculumDraftEntityType::Section->value || $lockedSection->archived_at !== null) {
                throw new RuntimeException('Content blocks can only be added to an available lesson section.');
            }
            $position = CurriculumDraftBlock::query()->where('curriculum_draft_entity_id', $lockedSection->id)->whereNull('archived_at')->max('position') + 1;
            $uuid = (string) Str::uuid();
            $asset = $this->availableAsset($lockedDraft, $asset);
            $normalized = $this->normalizeBlockPayload($lockedDraft, $lockedSection, $uuid, $type, $position, $payload, $asset);
            $block = CurriculumDraftBlock::create([
                'curriculum_draft_id' => $lockedDraft->id,
                'curriculum_draft_entity_id' => $lockedSection->id,
                'curriculum_asset_id' => $asset?->id,
                'block_uuid' => $uuid,
                'block_type' => $type->value,
                'position' => $position,
                'payload' => $normalized,
                'revision' => 1,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $lockedSection->increment('revision');
            $this->touchDraft($lockedDraft, $actor, 'block_created', ['entity_id' => $lockedSection->id, 'block_id' => $block->id, 'block_type' => $type->value]);

            return $block;
        }, attempts: 3);
    }

    /** @param array<string, mixed> $payload */
    public function updateBlock(
        CurriculumDraft $draft,
        CurriculumDraftBlock $block,
        User $actor,
        int $expectedDraftRevision,
        int $expectedBlockRevision,
        array $payload,
        ?CurriculumAsset $asset = null,
    ): CurriculumDraftBlock {
        return DB::transaction(function () use ($draft, $block, $actor, $expectedDraftRevision, $expectedBlockRevision, $payload, $asset): CurriculumDraftBlock {
            $lockedDraft = $this->editableDraft($draft, $expectedDraftRevision);
            $locked = CurriculumDraftBlock::query()->lockForUpdate()->where('curriculum_draft_id', $lockedDraft->id)->findOrFail($block->id);
            if ($locked->revision !== $expectedBlockRevision) {
                throw new CurriculumDraftConflictException($expectedBlockRevision, $locked->revision);
            }
            if ($locked->archived_at !== null) {
                throw new RuntimeException('An archived content block must be restored before it can be edited.');
            }
            $section = CurriculumDraftEntity::query()->lockForUpdate()->findOrFail($locked->curriculum_draft_entity_id);
            $type = CurriculumBlockType::from($locked->block_type);
            $asset = $this->availableAsset($lockedDraft, $asset);
            $locked->forceFill([
                'curriculum_asset_id' => $asset?->id,
                'payload' => $this->normalizeBlockPayload($lockedDraft, $section, $locked->block_uuid, $type, $locked->position, $payload, $asset),
                'revision' => $locked->revision + 1,
                'updated_by' => $actor->id,
            ])->save();
            $section->increment('revision');
            $this->touchDraft($lockedDraft, $actor, 'block_updated', ['block_id' => $locked->id]);

            return $locked->fresh();
        }, attempts: 3);
    }

    public function moveBlock(CurriculumDraft $draft, CurriculumDraftBlock $block, User $actor, int $expectedDraftRevision, int $expectedBlockRevision, int $newPosition): void
    {
        DB::transaction(function () use ($draft, $block, $actor, $expectedDraftRevision, $expectedBlockRevision, $newPosition): void {
            $lockedDraft = $this->editableDraft($draft, $expectedDraftRevision);
            $locked = CurriculumDraftBlock::query()->lockForUpdate()->where('curriculum_draft_id', $lockedDraft->id)->findOrFail($block->id);
            if ($locked->revision !== $expectedBlockRevision) {
                throw new CurriculumDraftConflictException($expectedBlockRevision, $locked->revision);
            }
            if ($locked->archived_at !== null) {
                throw new RuntimeException('An archived content block must be restored before it can be reordered.');
            }
            $this->persistBlockOrder($this->orderedBlocks($locked, $newPosition), $actor);
            CurriculumDraftEntity::query()->whereKey($locked->curriculum_draft_entity_id)->increment('revision');
            $this->touchDraft($lockedDraft, $actor, 'block_reordered', ['block_id' => $locked->id, 'position' => $newPosition]);
        }, attempts: 3);
    }

    public function setBlockArchived(CurriculumDraft $draft, CurriculumDraftBlock $block, User $actor, int $expectedDraftRevision, int $expectedBlockRevision, bool $archived): void
    {
        DB::transaction(function () use ($draft, $block, $actor, $expectedDraftRevision, $expectedBlockRevision, $archived): void {
            $lockedDraft = $this->editableDraft($draft, $expectedDraftRevision);
            $locked = CurriculumDraftBlock::query()->lockForUpdate()->where('curriculum_draft_id', $lockedDraft->id)->findOrFail($block->id);
            if ($locked->revision !== $expectedBlockRevision) {
                throw new CurriculumDraftConflictException($expectedBlockRevision, $locked->revision);
            }
            if ($archived === ($locked->archived_at !== null)) {
                throw new RuntimeException($archived ? 'This content block is already archived.' : 'This content block is already available.');
            }
            if (! $archived && ! CurriculumDraftEntity::query()->lockForUpdate()
                ->whereKey($locked->curriculum_draft_entity_id)->whereNull('archived_at')->exists()) {
                throw new RuntimeException('Restore the lesson section before restoring one of its content blocks.');
            }
            $locked->forceFill(['archived_at' => $archived ? now() : null, 'updated_by' => $actor->id]);
            if ($archived) {
                $locked->forceFill(['revision' => $locked->revision + 1])->save();
                $this->persistBlockOrder($this->availableBlockSiblings($locked->curriculum_draft_entity_id, $locked->id), $actor);
            } else {
                $this->persistBlockOrder($this->orderedBlocks($locked, max(1, (int) $locked->position)), $actor);
            }
            CurriculumDraftEntity::query()->whereKey($locked->curriculum_draft_entity_id)->increment('revision');
            $this->touchDraft($lockedDraft, $actor, $archived ? 'block_archived' : 'block_restored', ['block_id' => $locked->id]);
        }, attempts: 3);
    }

    private function clonePackage(CurriculumDraft $draft, CurriculumPackage $base, User $actor): void
    {
        CurriculumEntity::query()->where('curriculum_package_id', $base->id)->orderBy('id')->chunkById(100, function ($entities) use ($draft, $actor): void {
            foreach ($entities as $source) {
                $payload = $source->payload;
                $blocks = $source->entity_type === CurriculumDraftEntityType::Section->value ? array_values($payload['blocks'] ?? []) : [];
                unset($payload['blocks']);
                $entity = CurriculumDraftEntity::create([
                    'curriculum_draft_id' => $draft->id,
                    'source_entity_id' => $source->id,
                    'entity_uuid' => $source->entity_uuid,
                    'code' => $source->code,
                    'entity_type' => $source->entity_type,
                    'parent_code' => $source->parent_code,
                    'position' => $source->position,
                    'source_path' => $source->source_path,
                    'payload' => $payload,
                    'revision' => 1,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
                foreach ($blocks as $index => $block) {
                    CurriculumDraftBlock::create([
                        'curriculum_draft_id' => $draft->id,
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
        });
    }

    private function editableDraft(CurriculumDraft $draft, int $expectedRevision): CurriculumDraft
    {
        $locked = CurriculumDraft::query()->lockForUpdate()->findOrFail($draft->id);
        $this->lifecycle->assertRevision($locked, $expectedRevision);
        if ($locked->status !== CurriculumDraftStatus::Draft) {
            throw new RuntimeException('Only a draft-status workspace can be edited.');
        }

        return $locked;
    }

    private function lockedEntity(CurriculumDraft $draft, CurriculumDraftEntity $entity, int $expectedRevision): CurriculumDraftEntity
    {
        $locked = CurriculumDraftEntity::query()->lockForUpdate()->where('curriculum_draft_id', $draft->id)->findOrFail($entity->id);
        if ($locked->revision !== $expectedRevision) {
            throw new CurriculumDraftConflictException($expectedRevision, $locked->revision);
        }

        return $locked;
    }

    /** @param array<string, mixed> $metadata */
    private function touchDraft(CurriculumDraft $draft, User $actor, string $event, array $metadata): void
    {
        $draft->forceFill(['revision' => $draft->revision + 1, 'updated_by' => $actor->id])->save();
        $this->lifecycle->record($draft, $actor, $event, metadata: $metadata);
    }

    /** @param Collection<int, CurriculumDraftEntity> $entities */
    private function persistEntityOrder(Collection $entities, User $actor): void
    {
        foreach ($entities as $index => $entity) {
            $payload = $entity->payload;
            if ($entity->entity_type === CurriculumDraftEntityType::Chapter->value) {
                $payload['module'] = $index + 1;
            } elseif ($entity->entity_type === CurriculumDraftEntityType::Section->value) {
                $payload['order'] = $index + 1;
            }
            $entity->forceFill(['position' => $index + 1, 'payload' => $payload, 'updated_by' => $actor->id]);
            if ($entity->isDirty()) {
                $entity->revision++;
                $entity->save();
            }
        }
    }

    /** @return Collection<int, CurriculumDraftEntity> */
    private function orderedEntities(CurriculumDraft $draft, CurriculumDraftEntity $entity, int $position): Collection
    {
        $siblings = $this->availableEntitySiblings($draft, $entity->entity_type, $entity->parent_code, $entity->id);
        $siblings->splice(max(0, min($position - 1, $siblings->count())), 0, [$entity]);

        return $siblings;
    }

    /** @return Collection<int, CurriculumDraftEntity> */
    private function availableEntitySiblings(CurriculumDraft $draft, string $type, ?string $parentCode, int $excludedId): Collection
    {
        return CurriculumDraftEntity::query()->lockForUpdate()
            ->where('curriculum_draft_id', $draft->id)
            ->where('entity_type', $type)
            ->where('parent_code', $parentCode)
            ->whereKeyNot($excludedId)
            ->whereNull('archived_at')
            ->orderBy('position')->orderBy('id')->get();
    }

    /** @param Collection<int, CurriculumDraftBlock> $blocks */
    private function persistBlockOrder(Collection $blocks, User $actor): void
    {
        foreach ($blocks as $index => $block) {
            $payload = $block->payload;
            $payload['order'] = $index + 1;
            $block->forceFill(['position' => $index + 1, 'payload' => $payload, 'updated_by' => $actor->id]);
            if ($block->isDirty()) {
                $block->revision++;
                $block->save();
            }
        }
    }

    /** @return Collection<int, CurriculumDraftBlock> */
    private function orderedBlocks(CurriculumDraftBlock $block, int $position): Collection
    {
        $siblings = $this->availableBlockSiblings($block->curriculum_draft_entity_id, $block->id);
        $siblings->splice(max(0, min($position - 1, $siblings->count())), 0, [$block]);

        return $siblings;
    }

    /** @return Collection<int, CurriculumDraftBlock> */
    private function availableBlockSiblings(int $entityId, int $excludedId): Collection
    {
        return CurriculumDraftBlock::query()->lockForUpdate()
            ->where('curriculum_draft_entity_id', $entityId)
            ->whereKeyNot($excludedId)
            ->whereNull('archived_at')
            ->orderBy('position')->orderBy('id')->get();
    }

    /** @return list<int> */
    private function descendantIds(CurriculumDraft $draft, CurriculumDraftEntity $root): array
    {
        $ids = [$root->id];
        $codes = $root->code === null ? [] : [$root->code];
        while ($codes !== []) {
            $children = CurriculumDraftEntity::query()->lockForUpdate()->where('curriculum_draft_id', $draft->id)
                ->whereIn('parent_code', $codes)->whereNull('archived_at')->get(['id', 'code']);
            if ($children->isEmpty()) {
                break;
            }
            $ids = array_merge($ids, $children->pluck('id')->map(fn ($id): int => (int) $id)->all());
            $codes = $children->pluck('code')->filter()->values()->all();
        }

        return array_values(array_unique($ids));
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function newEntityPayload(CurriculumDraft $draft, CurriculumDraftEntityType $type, string $code, array $data): array
    {
        $common = ['id' => (string) Str::uuid(), 'code' => $code, 'content_version' => $draft->content_version, 'entity_type' => $type->value, 'status' => 'draft'];

        return match ($type) {
            CurriculumDraftEntityType::Chapter => array_merge($common, [
                'module' => (int) $data['position'], 'title' => $data['title'], 'outcome_codes' => [],
                'replaces' => null, 'replaced_by' => null, 'source_locator' => $this->manualLocator($draft, $data['title']),
            ]),
            CurriculumDraftEntityType::Section => array_merge($common, [
                'chapter_code' => $data['parent_code'], 'language' => 'en', 'order' => (int) $data['position'],
                'title' => $data['title'], 'provenance_kind' => 'derived_navigation', 'replaces' => null,
                'replaced_by' => null, 'source_locator' => $this->manualLocator($draft, $data['title']),
            ]),
            CurriculumDraftEntityType::Outcome => [
                'id' => $code, 'module' => (int) $data['module'], 'statement' => $data['statement'],
                'type' => $data['outcome_type'], 'provisional_band' => $data['provisional_band'],
                'status' => 'draft_pending_qualified_review', 'cefr_reference_ids' => [], 'competency_ids' => [],
                'evidence_conditions' => [],
            ],
        };
    }

    private function newSourcePath(CurriculumDraftEntityType $type, string $code, string $parent): string
    {
        return match ($type) {
            CurriculumDraftEntityType::Chapter => "chapters/{$code}/chapter.json",
            CurriculumDraftEntityType::Section => "chapters/{$parent}/sections/{$code}.json",
            CurriculumDraftEntityType::Outcome => "framework/outcome-alignments.json#/outcomes/{$code}",
        };
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function normalizeBlockPayload(CurriculumDraft $draft, CurriculumDraftEntity $section, string $uuid, CurriculumBlockType $type, int $position, array $payload, ?CurriculumAsset $asset): array
    {
        $normalized = $payload;
        $text = trim((string) ($payload['text'] ?? $payload['caption'] ?? $payload['instruction'] ?? $type->value));
        $normalized['id'] = $uuid;
        $normalized['type'] = $type->value;
        $normalized['order'] = $position;
        $normalized['language'] = 'en';
        $normalized['provenance_kind'] = isset($payload['provenance_note']) ? 'admin_authored' : 'derived_navigation';
        $normalized['source_locator'] = $this->manualLocator($draft, $section->code.': '.$text, $section);
        if ($asset !== null) {
            $asset->loadMissing('blob');
            $normalized['asset'] = [
                'id' => $asset->public_id,
                'kind' => $asset->kind->value,
                'display_name' => $asset->display_name,
                'accessibility_text' => $asset->accessibility_text,
                'rights_basis' => $asset->rights_basis,
                'source_url' => $asset->source_url,
                'sha256' => $asset->blob->sha256,
                'mime_type' => $asset->blob->detected_mime,
            ];
        } else {
            unset($normalized['asset']);
        }

        return $normalized;
    }

    /** @return array<string, mixed> */
    private function manualLocator(CurriculumDraft $draft, string $text, ?CurriculumDraftEntity $section = null): array
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return [
            'artifact' => 'Hospitrainity canonical authoring workspace',
            'body_index' => 0,
            'chapter' => $section === null ? 0 : (int) (CurriculumDraftEntity::query()
                ->where('curriculum_draft_id', $draft->id)->where('code', $section->parent_code)->value('position') ?? 0),
            'heading_path' => [$draft->title],
            'normalized_text_sha256' => hash('sha256', $normalized),
        ];
    }

    private function availableAsset(CurriculumDraft $draft, ?CurriculumAsset $asset): ?CurriculumAsset
    {
        if ($asset === null) {
            return null;
        }

        return CurriculumAsset::query()->whereKey($asset->id)
            ->where('curriculum_draft_id', $draft->id)->whereNull('archived_at')->firstOrFail();
    }
}
