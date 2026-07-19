<?php

namespace App\Services\Curriculum;

use RuntimeException;

final class StandaloneProjectionBuilder
{
    /** @return array<string, mixed> */
    public function build(CanonicalPackage $package): array
    {
        $byType = [];
        foreach ($package->entities as $entity) {
            $byType[$entity['entity_type']][] = $entity;
        }

        $sectionsByCode = $this->keyByCode($byType['lesson-section'] ?? []);
        $promptsByActivity = $this->groupByParent($byType['prompt-item'] ?? []);
        $answersByPrompt = $this->keyByParent($byType['answer-model'] ?? []);
        $feedbackByPrompt = $this->keyByParent($byType['feedback-model'] ?? []);
        $rubricsByActivity = $this->keyByParent($byType['rubric'] ?? []);

        $activitiesByChapter = [];
        foreach ($byType['activity'] ?? [] as $activity) {
            $section = $sectionsByCode[$activity['parent_code']] ?? throw new RuntimeException(
                "Activity {$activity['code']} refers to a missing section."
            );
            $activitiesByChapter[$section['parent_code']][] = $this->activity(
                $activity,
                $promptsByActivity[$activity['code']] ?? [],
                $answersByPrompt,
                $feedbackByPrompt,
                $rubricsByActivity[$activity['code']] ?? null,
            );
        }

        $sectionsByChapter = $this->groupByParent($byType['lesson-section'] ?? []);
        $chapters = $byType['chapter'] ?? [];
        usort($chapters, static fn (array $left, array $right): int => $left['payload']['module'] <=> $right['payload']['module']);

        $chapterProjection = [];
        foreach ($chapters as $chapter) {
            $sections = $sectionsByChapter[$chapter['code']] ?? [];
            usort($sections, static fn (array $left, array $right): int => $left['position'] <=> $right['position']);

            $activities = $activitiesByChapter[$chapter['code']] ?? [];
            usort($activities, static fn (array $left, array $right): int => strcmp($left['code'], $right['code']));

            $chapterProjection[] = [
                'activities' => $activities,
                'code' => $chapter['code'],
                'module' => (int) $chapter['payload']['module'],
                'outcome_codes' => array_values($chapter['payload']['outcome_codes'] ?? []),
                'sections' => array_map(static fn (array $section): array => [
                    'blocks' => array_values($section['payload']['blocks'] ?? []),
                    'code' => $section['code'],
                    'order' => (int) $section['position'],
                    'title' => $section['payload']['title'],
                ], $sections),
                'status' => $chapter['lifecycle_status'],
                'title' => $chapter['payload']['title'],
            ];
        }

        $outcomes = array_map(static fn (array $outcome): array => [
            'id' => $outcome['code'],
            'module' => (int) $outcome['payload']['module'],
            'provisional_band' => $outcome['payload']['provisional_band'],
            'statement' => $outcome['payload']['statement'],
            'type' => $outcome['payload']['type'],
        ], $byType['outcome'] ?? []);
        usort($outcomes, static fn (array $left, array $right): int => strcmp($left['id'], $right['id']));

        $meta = $package->evidence['projection_meta'];
        $meta['content_version'] = $package->metadata['content_version'];

        return [
            'chapters' => $chapterProjection,
            'meta' => $meta,
            'outcomes' => $outcomes,
            'stats' => [
                'activities' => $package->counts['activities'],
                'chapters' => $package->counts['chapters'],
                'outcomes' => $package->counts['outcomes'],
                'prompts' => $package->counts['prompts'],
                'sections' => $package->counts['sections'],
            ],
            'workflow' => [
                'gate' => $package->evidence['workflow_gate'],
                'modules' => array_map(static fn (array $chapter): array => [
                    'approval_gate' => strtoupper($chapter['status']),
                    'code' => $chapter['code'],
                    'module' => $chapter['module'],
                    'state' => $chapter['status'],
                ], $chapterProjection),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $activity
     * @param  list<array<string, mixed>>  $prompts
     * @param  array<string, array<string, mixed>>  $answersByPrompt
     * @param  array<string, array<string, mixed>>  $feedbackByPrompt
     * @param  array<string, mixed>|null  $rubric
     * @return array<string, mixed>
     */
    private function activity(
        array $activity,
        array $prompts,
        array $answersByPrompt,
        array $feedbackByPrompt,
        ?array $rubric,
    ): array {
        usort($prompts, static fn (array $left, array $right): int => [
            $left['position'] ?? PHP_INT_MAX, $left['code'],
        ] <=> [
            $right['position'] ?? PHP_INT_MAX, $right['code'],
        ]);

        return [
            'accessibility' => array_values($activity['payload']['accessibility'] ?? []),
            'cefr_activity' => $activity['payload']['cefr_activity'],
            'channel' => $activity['payload']['channel'],
            'code' => $activity['code'],
            'lesson_code' => $activity['payload']['lesson_code'],
            'outcome_codes' => array_values($activity['payload']['outcome_codes'] ?? []),
            'participation' => $activity['payload']['participation'],
            'pedagogical_function' => $activity['payload']['pedagogical_function'],
            'guidance' => $activity['payload']['guidance'] ?? null,
            'completion_rule' => $activity['payload']['completion_rule'] ?? null,
            'prompts' => array_map(static function (array $prompt) use ($answersByPrompt, $feedbackByPrompt): array {
                $answer = $answersByPrompt[$prompt['code']] ?? null;
                $feedback = $feedbackByPrompt[$prompt['code']] ?? null;
                $form = $prompt['payload']['response_form'];
                if ($form !== 'rating' && ($answer === null || $feedback === null)) {
                    throw new RuntimeException("Non-rating prompt {$prompt['code']} has incomplete answer/feedback models.");
                }

                return [
                    'answers' => array_values($answer['payload']['accepted'] ?? []),
                    'accepted_normalized' => array_values($answer['payload']['accepted_normalized'] ?? []),
                    'choices' => array_values($prompt['payload']['choices'] ?? []),
                    'code' => $prompt['code'],
                    'correct_choice_ids' => array_values($answer['payload']['correct_choice_ids'] ?? []),
                    'correct_order' => array_values($answer['payload']['correct_order'] ?? []),
                    'feedback' => array_values($feedback['payload']['messages'] ?? []),
                    'rating_scale' => $prompt['payload']['rating_scale'] ?? null,
                    'response_constraints' => $prompt['payload']['response_constraints'] ?? null,
                    'response_form' => $form,
                    'scoring_mode' => $prompt['payload']['scoring_mode'] ?? $answer['payload']['scoring_mode'] ?? null,
                    'self_check_required' => (bool) ($prompt['payload']['self_check_required'] ?? false),
                    'stem' => $prompt['payload']['stem'],
                    'tokens' => array_values($prompt['payload']['tokens'] ?? []),
                ];
            }, $prompts),
            'response_form' => $activity['payload']['response_form'],
            'rubric' => $rubric === null ? null : [
                'code' => $rubric['code'],
                'criteria' => array_values($rubric['payload']['criteria'] ?? []),
            ],
            'scoring_mode' => $activity['payload']['scoring_mode'],
            'timing' => $activity['payload']['timing'],
            'title' => $activity['payload']['title'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $entities
     * @return array<string, array<string, mixed>>
     */
    private function keyByCode(array $entities): array
    {
        $keyed = [];
        foreach ($entities as $entity) {
            $keyed[$entity['code']] = $entity;
        }

        return $keyed;
    }

    /**
     * @param  list<array<string, mixed>>  $entities
     * @return array<string, array<string, mixed>>
     */
    private function keyByParent(array $entities): array
    {
        $keyed = [];
        foreach ($entities as $entity) {
            if (isset($keyed[$entity['parent_code']])) {
                throw new RuntimeException("More than one {$entity['entity_type']} targets {$entity['parent_code']}.");
            }
            $keyed[$entity['parent_code']] = $entity;
        }

        return $keyed;
    }

    /**
     * @param  list<array<string, mixed>>  $entities
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupByParent(array $entities): array
    {
        $grouped = [];
        foreach ($entities as $entity) {
            $grouped[$entity['parent_code']][] = $entity;
        }

        return $grouped;
    }
}
