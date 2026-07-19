<?php

namespace App\Services\Curriculum;

use App\Models\CurriculumDraft;
use App\Models\CurriculumDraftEntity;

final class CurriculumDraftProjection
{
    /** @return list<array<string, mixed>> */
    public function entities(CurriculumDraft $draft, bool $forPublication = false): array
    {
        $entities = CurriculumDraftEntity::query()
            ->where('curriculum_draft_id', $draft->id)
            ->whereNull('archived_at')
            ->with(['blocks' => fn ($query) => $query->whereNull('archived_at')->with('asset.blob')->orderBy('position')->orderBy('id')])
            ->orderBy('source_path')
            ->get();

        return $entities->map(function (CurriculumDraftEntity $entity) use ($draft, $forPublication): array {
            $payload = $entity->payload;
            if ($entity->entity_type === 'lesson-section') {
                $payload['blocks'] = $entity->blocks->values()->map(function ($block, int $index): array {
                    $payload = $block->payload;
                    $payload['order'] = $index + 1;
                    if ($block->asset !== null) {
                        $payload['asset'] = array_replace($payload['asset'] ?? [], [
                            'id' => $block->asset->public_id,
                            'kind' => $block->asset->kind->value,
                            'display_name' => $block->asset->display_name,
                            'accessibility_text' => $block->asset->accessibility_text,
                            'rights_basis' => $block->asset->rights_basis,
                            'source_url' => $block->asset->source_url,
                            'sha256' => $block->asset->blob->sha256,
                            'mime_type' => $block->asset->blob->detected_mime,
                            'path' => 'assets/'.$block->asset->blob->sha256.'.'.$block->asset->blob->extension,
                        ]);
                    }

                    return $payload;
                })->all();
            }
            if ($forPublication) {
                if (array_key_exists('content_version', $payload)) {
                    $payload['content_version'] = $draft->content_version;
                }
                if (in_array($entity->entity_type, ['chapter', 'lesson-section', 'activity', 'prompt-item', 'answer-model', 'feedback-model', 'rubric'], true)) {
                    $payload['status'] = 'published';
                }
            }

            return [
                'entity_uuid' => $entity->entity_uuid,
                'code' => $entity->code,
                'entity_type' => $entity->entity_type,
                'parent_code' => $entity->parent_code,
                'position' => $entity->position,
                'lifecycle_status' => $forPublication && in_array($entity->entity_type, ['chapter', 'lesson-section', 'activity', 'prompt-item', 'answer-model', 'feedback-model', 'rubric'], true)
                    ? 'published'
                    : ($payload['status'] ?? null),
                'content_version' => $forPublication && array_key_exists('content_version', $payload)
                    ? $draft->content_version
                    : ($payload['content_version'] ?? null),
                'source_path' => $entity->source_path,
                'source_sha256' => hash('sha256', CanonicalJson::encode($payload)),
                'payload' => $payload,
            ];
        })->all();
    }

    /** @return array<string, int> */
    public function counts(CurriculumDraft $draft): array
    {
        $map = [
            'chapters' => 'chapter', 'sections' => 'lesson-section', 'activities' => 'activity',
            'prompts' => 'prompt-item', 'answer_models' => 'answer-model', 'feedback_models' => 'feedback-model',
            'rubrics' => 'rubric', 'outcomes' => 'outcome', 'competencies' => 'competency',
            'cefr_references' => 'cefr-reference', 'source_provenance' => 'source-provenance', 'migration_edges' => 'migration-edge',
        ];
        $counts = [];
        foreach ($map as $key => $type) {
            $counts[$key] = CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)
                ->whereNull('archived_at')->where('entity_type', $type)->count();
        }

        return $counts;
    }

    /** @return array<string, int> */
    public function coverage(CurriculumDraft $draft): array
    {
        $entities = $this->entities($draft);
        $blocks = [];
        foreach ($entities as $entity) {
            if ($entity['entity_type'] === 'lesson-section') {
                array_push($blocks, ...($entity['payload']['blocks'] ?? []));
            }
        }

        return [
            'content_blocks' => count($blocks),
            'source_tables' => count(array_filter($blocks, fn (array $block): bool => ($block['type'] ?? null) === 'source_table')),
            'external_hyperlink_relationships' => array_sum(array_map(fn (array $block): int => ($block['type'] ?? null) === 'external_link' ? count($block['links'] ?? []) : 0, $blocks)),
        ];
    }
}
