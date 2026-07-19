<?php

namespace App\Services\Curriculum;

use App\Models\CurriculumDraft;
use Illuminate\Support\Collection;

final class CurriculumDraftPreviewRepository
{
    public function __construct(private readonly CurriculumDraftProjection $projection) {}

    /** @return Collection<int, array<string, mixed>> */
    public function chapters(CurriculumDraft $draft): Collection
    {
        $entities = collect($this->projection->entities($draft));
        $sections = $entities->where('entity_type', 'lesson-section')->groupBy('parent_code');
        $sectionChapter = $entities->where('entity_type', 'lesson-section')->pluck('parent_code', 'code');
        $activitiesByChapter = $entities->where('entity_type', 'activity')->groupBy(
            fn (array $activity): ?string => $sectionChapter[$activity['parent_code']] ?? null,
        );

        return $entities->where('entity_type', 'chapter')->sortBy(fn (array $chapter): int => (int) $chapter['payload']['module'])
            ->values()->map(fn (array $chapter): array => [
                'code' => $chapter['code'], 'title' => $chapter['payload']['title'],
                'module' => (int) $chapter['payload']['module'], 'status' => 'draft preview',
                'sections_count' => ($sections[$chapter['code']] ?? collect())->count(),
                'activities_count' => ($activitiesByChapter[$chapter['code']] ?? collect())->count(),
                'progress' => 0, 'completed_activities' => 0,
            ]);
    }

    /** @return array<string, mixed>|null */
    public function chapter(CurriculumDraft $draft, string $code): ?array
    {
        $entities = collect($this->projection->entities($draft));
        $chapter = $entities->first(fn (array $item): bool => $item['entity_type'] === 'chapter' && $item['code'] === $code);
        if ($chapter === null) {
            return null;
        }
        $sections = $entities->filter(fn (array $item): bool => $item['entity_type'] === 'lesson-section' && $item['parent_code'] === $code)
            ->sortBy('position')->values();
        $activities = $entities->where('entity_type', 'activity')->keyBy('parent_code');
        $outcomes = $entities->where('entity_type', 'outcome')->keyBy('code');

        return [
            'code' => $chapter['code'], 'title' => $chapter['payload']['title'],
            'module' => (int) $chapter['payload']['module'], 'status' => 'draft preview',
            'source_locator' => $chapter['payload']['source_locator'] ?? null,
            'outcomes' => collect($chapter['payload']['outcome_codes'] ?? [])->map(
                fn (string $outcomeCode): ?array => isset($outcomes[$outcomeCode])
                    ? array_merge(['code' => $outcomeCode], $outcomes[$outcomeCode]['payload']) : null,
            )->filter()->values(),
            'sections' => $sections->map(function (array $section) use ($activities): array {
                $activity = $activities[$section['code']] ?? null;

                return [
                    'code' => $section['code'], 'title' => $section['payload']['title'],
                    'order' => (int) $section['position'], 'status' => 'draft preview',
                    'source_locator' => $section['payload']['source_locator'] ?? null,
                    'activity' => $activity === null ? null : [
                        'code' => $activity['code'], 'title' => $activity['payload']['title'],
                        'response_form' => $activity['payload']['response_form'],
                        'scoring_mode' => $activity['payload']['scoring_mode'],
                    ],
                ];
            })->values(),
            'package' => (object) ['content_version' => $draft->content_version],
        ];
    }

    /** @return array<string, mixed>|null */
    public function section(CurriculumDraft $draft, string $code): ?array
    {
        $entities = collect($this->projection->entities($draft));
        $section = $entities->first(fn (array $item): bool => $item['entity_type'] === 'lesson-section' && $item['code'] === $code);
        if ($section === null) {
            return null;
        }
        $chapter = $entities->first(fn (array $item): bool => $item['entity_type'] === 'chapter' && $item['code'] === $section['parent_code']);
        if ($chapter === null) {
            return null;
        }
        $activity = $entities->first(fn (array $item): bool => $item['entity_type'] === 'activity' && $item['parent_code'] === $code);
        $chapterOrder = $entities->where('entity_type', 'chapter')->mapWithKeys(fn (array $item): array => [$item['code'] => $item['payload']['module']]);
        $sequence = $entities->where('entity_type', 'lesson-section')->sortBy(fn (array $item): string => sprintf('%03d-%03d', $chapterOrder[$item['parent_code']] ?? PHP_INT_MAX, $item['position']))->values();
        $current = $sequence->search(fn (array $item): bool => $item['code'] === $code);
        if ($current === false) {
            return null;
        }
        $nav = fn (?array $item): ?array => $item === null ? null : ['code' => $item['code'], 'title' => $item['payload']['title']];
        $routePrefix = auth()->user()?->isSuperAdmin() ? 'superadmin' : 'admin';
        $blocks = array_map(function (array $block) use ($draft, $routePrefix): array {
            if (is_string($block['asset']['id'] ?? null)) {
                $block['asset']['url'] = route($routePrefix.'.curriculum-drafts.assets.show', [$draft, $block['asset']['id']]);
            }

            return $block;
        }, array_values($section['payload']['blocks'] ?? []));

        return [
            'code' => $section['code'], 'title' => $section['payload']['title'], 'order' => (int) $section['position'],
            'status' => 'draft preview', 'blocks' => $blocks,
            'chapter' => ['code' => $chapter['code'], 'title' => $chapter['payload']['title'], 'module' => (int) $chapter['payload']['module']],
            'activity' => $activity === null ? null : ['code' => $activity['code'], 'title' => $activity['payload']['title'], 'response_form' => $activity['payload']['response_form']],
            'navigation' => [
                'position' => $current + 1, 'total' => $sequence->count(),
                'previous' => $nav($current > 0 ? $sequence[$current - 1] : null),
                'next' => $nav($current + 1 < $sequence->count() ? $sequence[$current + 1] : null),
            ],
            'package' => (object) ['content_version' => $draft->content_version],
        ];
    }

    /** @return array<string, mixed>|null */
    public function activity(CurriculumDraft $draft, string $code): ?array
    {
        $entities = collect($this->projection->entities($draft));
        $activity = $entities->first(fn (array $item): bool => $item['entity_type'] === 'activity' && $item['code'] === $code);
        if ($activity === null) {
            return null;
        }
        $section = $entities->first(fn (array $item): bool => $item['entity_type'] === 'lesson-section' && $item['code'] === $activity['parent_code']);
        $chapter = $section === null ? null : $entities->first(fn (array $item): bool => $item['entity_type'] === 'chapter' && $item['code'] === $section['parent_code']);
        if ($section === null || $chapter === null) {
            return null;
        }
        $prompts = $entities->filter(fn (array $item): bool => $item['entity_type'] === 'prompt-item' && $item['parent_code'] === $code)
            ->sortBy(fn (array $item): array => [$item['position'] ?? PHP_INT_MAX, $item['code']])->values();
        $models = $entities->filter(fn (array $item): bool => in_array($item['entity_type'], ['answer-model', 'feedback-model'], true))->groupBy('parent_code');
        $rubric = $entities->first(fn (array $item): bool => $item['entity_type'] === 'rubric' && $item['parent_code'] === $code);
        $outcomes = $entities->where('entity_type', 'outcome')->keyBy('code');

        return [
            'id' => 0, 'code' => $activity['code'], 'title' => $activity['payload']['title'], 'status' => 'draft preview',
            'metadata' => collect($activity['payload'])->only(['accessibility', 'cefr_activity', 'channel', 'participation', 'pedagogical_function', 'response_form', 'scoring_mode', 'timing', 'source_locator'])->all(),
            'guidance' => $activity['payload']['guidance'] ?? null,
            'completion_rule' => $activity['payload']['completion_rule'] ?? null,
            'chapter' => ['code' => $chapter['code'], 'title' => $chapter['payload']['title'], 'module' => (int) $chapter['payload']['module']],
            'section' => ['code' => $section['code'], 'title' => $section['payload']['title']],
            'prompts' => $prompts->map(function (array $prompt) use ($models, $draft): array {
                $answer = ($models[$prompt['code']] ?? collect())->firstWhere('entity_type', 'answer-model');

                $audio = $prompt['payload']['audio_asset'] ?? null;
                if (is_array($audio) && is_string($audio['id'] ?? null)) {
                    $prefix = auth()->user()?->isSuperAdmin() ? 'superadmin' : 'admin';
                    $audio['url'] = route($prefix.'.curriculum-drafts.assets.show', [$draft, $audio['id']]);
                }

                return [
                    'code' => $prompt['code'], 'stem' => $prompt['payload']['stem'],
                    'response_form' => $prompt['payload']['response_form'],
                    'choices' => array_map(fn (array $choice): array => ['id' => $choice['id'], 'label' => $choice['label'], 'text' => $choice['text']], array_values($prompt['payload']['choices'] ?? [])),
                    'rating_scale' => $prompt['payload']['rating_scale'] ?? null,
                    'response_constraints' => $prompt['payload']['response_constraints'] ?? null,
                    'scoring_mode' => $prompt['payload']['scoring_mode'] ?? $answer['payload']['scoring_mode'] ?? null,
                    'self_check_required' => (bool) ($prompt['payload']['self_check_required'] ?? false),
                    'source_locator' => $prompt['payload']['source_locator'] ?? null,
                    'tokens' => array_values($prompt['payload']['tokens'] ?? []),
                    'audio' => $audio,
                ];
            })->values(),
            'rubric' => $rubric['payload'] ?? null,
            'outcomes' => collect($activity['payload']['outcome_codes'] ?? [])->map(fn (string $outcomeCode): ?array => isset($outcomes[$outcomeCode]) ? array_merge(['code' => $outcomeCode], $outcomes[$outcomeCode]['payload']) : null)->filter()->values(),
            'progress' => ['state' => 'preview_not_recorded', 'completed' => false, 'legacy_reveal_only' => false, 'attempt_count' => 0],
            'completed' => false,
            'package' => (object) ['content_version' => $draft->content_version],
        ];
    }
}
