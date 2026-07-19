<?php

namespace App\Services\Curriculum;

use App\Models\CurriculumDraft;
use App\Models\CurriculumDraftEntity;

final class CurriculumDraftDiff
{
    public function __construct(private readonly CurriculumDraftProjection $projection) {}

    /** @return array<string, mixed> */
    public function report(CurriculumDraft $draft): array
    {
        $projected = collect($this->projection->entities($draft))->keyBy('source_path');
        $items = [];
        foreach (CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)->with('sourceEntity')->orderBy('source_path')->get() as $entity) {
            $current = $projected->get($entity->source_path);
            $base = $entity->sourceEntity?->payload;
            $change = match (true) {
                $entity->archived_at !== null => 'archived',
                $base === null => 'created',
                $current !== null && hash_equals(
                    hash('sha256', CanonicalJson::encode($base)),
                    hash('sha256', CanonicalJson::encode($current['payload'])),
                ) => 'unchanged',
                default => 'changed',
            };
            $items[] = [
                'change' => $change,
                'code' => $entity->code,
                'entity_type' => $entity->entity_type,
                'source_path' => $entity->source_path,
                'source_locator' => $current['payload']['source_locator'] ?? $base['source_locator'] ?? null,
            ];
        }

        $summary = collect($items)->countBy('change')->all();
        foreach (['created', 'changed', 'archived', 'unchanged'] as $key) {
            $summary[$key] = (int) ($summary[$key] ?? 0);
        }

        return [
            'report_version' => '1.0.0',
            'draft_id' => $draft->public_id,
            'draft_revision' => $draft->revision,
            'base_content_version' => $draft->basePackage?->content_version,
            'proposed_content_version' => $draft->content_version,
            'summary' => $summary,
            'items' => $items,
        ];
    }
}
