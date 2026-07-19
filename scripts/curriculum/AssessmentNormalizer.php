<?php

declare(strict_types=1);

namespace Hospitrainity\Curriculum;

use RuntimeException;

final class AssessmentNormalizer
{
    private const OPEN_SHORT_TEXT = [
        'HSP-C02-PR-I4',
        'HSP-C05-PR-I1',
        'HSP-C05-PR-I4',
        'HSP-C06-PR-I4',
        'HSP-C07-PR-I4',
    ];

    /** @var array<string, list<string>> */
    private const CLOSED_SHORT_TEXT_VARIANTS = [
        'HSP-C02-PR-I1' => ['see'],
        'HSP-C03-PR-I1' => ['put'],
        'HSP-C03-PR-I4' => ['four five two'],
        'HSP-C04-PR-I1' => ['read'],
        'HSP-C04-PR-I4' => ['three oh five'],
        'HSP-C07-PR-I1' => ['apologise and empathise', 'the apologise and empathise step'],
    ];

    /**
     * @param  list<array<string, mixed>>  $chapters
     * @param  array<string, array{start:int,end:int,heading:array<string,mixed>}>  $ranges
     * @return array<string, int>
     */
    public static function normalize(SourceCompiler $compiler, array $chapters, array $ranges): array
    {
        $root = $compiler->outputPath();
        if (self::isCanonicalBaseline($compiler)) {
            return self::refreshCanonicalBaseline($compiler, $chapters);
        }
        $guidanceStrings = 0;

        // Six current confidence "prompts" are source instructions, not learner
        // response items. Keep their exact wording as activity guidance.
        foreach (glob($root.'/assessment/*/prompts/*.json') ?: [] as $promptPath) {
            $prompt = $compiler->readJson($promptPath);
            if (($prompt['response_form'] ?? null) !== 'self_rating') {
                continue;
            }
            $activityPath = self::activityPath($root, (string) $prompt['activity_code']);
            $activity = $compiler->readJson($activityPath);
            $activity['guidance'] = (string) $prompt['stem'];
            $activity['guidance_provenance_kind'] = 'source_verbatim';
            $activity['scoring_mode'] = 'unscored_self_report';
            $compiler->writeJson($activityPath, $activity);
            self::unlinkRequired($promptPath);
            self::unlinkRequired(self::siblingModelPath($promptPath, 'answers', '-AM'));
            self::unlinkRequired(self::siblingModelPath($promptPath, 'feedback', '-FB'));
            $guidanceStrings++;
        }
        // A canonical package has already moved these six source instructions
        // from prompt files onto their activities. Accept that normalized
        // representation so the authority compiler can safely use the active
        // canonical package as its next baseline.
        if ($guidanceStrings === 0) {
            foreach (glob($root.'/assessment/*/activities/*.json') ?: [] as $activityPath) {
                $activity = $compiler->readJson($activityPath);
                if (str_ends_with((string) ($activity['code'] ?? ''), '-ACT-CONFIDENCE')
                    && ($activity['guidance_provenance_kind'] ?? null) === 'source_verbatim'
                    && is_string($activity['guidance'] ?? null)
                    && trim($activity['guidance']) !== '') {
                    $guidanceStrings++;
                }
            }
        }
        if ($guidanceStrings !== 6) {
            throw new RuntimeException("Expected six confidence instructions; found {$guidanceStrings}.");
        }

        $counts = [
            'exercise_prompts' => 0,
            'rating_items' => 0,
            'selection_prompts' => 0,
            'ordering_prompts' => 0,
            'short_text_prompts' => 0,
            'role_play_prompts' => 0,
            'service_artifact_prompts' => 0,
            'accepted_strings' => 0,
            'feedback_strings' => $guidanceStrings,
        ];

        foreach (glob($root.'/assessment/*/prompts/*.json') ?: [] as $promptPath) {
            $prompt = $compiler->readJson($promptPath);
            $chapter = (int) substr((string) $prompt['code'], 5, 2);
            $source = $compiler->findSourceElement((string) $prompt['stem'], $chapter);
            $heading = self::headingPath($compiler, $chapters, (string) $prompt['activity_code']);
            $prompt['source_locator'] = $compiler->sourceTextLocator($source, (string) $prompt['stem'], $chapter, $heading);
            $prompt['language'] = 'en';
            $prompt['provenance_kind'] = 'source_verbatim';

            $answerPath = self::siblingModelPath($promptPath, 'answers', '-AM');
            $feedbackPath = self::siblingModelPath($promptPath, 'feedback', '-FB');
            $answer = $compiler->readJson($answerPath);
            $feedback = $compiler->readJson($feedbackPath);
            $counts['accepted_strings'] += count(array_filter($answer['accepted'] ?? [], static fn (mixed $value): bool => is_string($value) && $value !== ''));
            $counts['feedback_strings'] += count($feedback['messages'] ?? []);

            $messages = $feedback['messages'] ?? [];
            $form = (string) $prompt['response_form'];
            if ($form === 'selection') {
                $choices = self::choices($compiler, (string) $prompt['code'], (string) $prompt['stem'], $messages);
                $correctLabel = self::acceptedChoiceLabel($answer['accepted'] ?? []);
                $correctChoices = array_values(array_filter(
                    $choices,
                    static fn (array $choice): bool => strtolower((string) $choice['label']) === $correctLabel,
                ));
                if (count($correctChoices) !== 1) {
                    throw new RuntimeException("Selection prompt {$prompt['code']} does not resolve to exactly one source answer.");
                }
                $prompt['choices'] = $choices;
                $prompt['scoring_mode'] = 'objective_choice';
                $answer['scoring_mode'] = 'objective_choice';
                $answer['correct_choice_ids'] = [(string) $correctChoices[0]['id']];
                $counts['selection_prompts']++;
            } elseif ($form === 'ordering') {
                [$tokens, $correctOrder] = self::ordering($compiler, (string) $prompt['code'], (string) $prompt['stem'], (string) (($answer['accepted'][0] ?? '')));
                $prompt['tokens'] = $tokens;
                $prompt['scoring_mode'] = 'objective_ordered';
                $answer['scoring_mode'] = 'objective_ordered';
                $answer['correct_order'] = $correctOrder;
                $counts['ordering_prompts']++;
            } elseif ($form === 'short_text') {
                $open = in_array($prompt['code'], self::OPEN_SHORT_TEXT, true);
                $prompt['scoring_mode'] = $open ? 'model_self_check' : 'objective_normalized_closed';
                $answer['scoring_mode'] = $prompt['scoring_mode'];
                if ($open) {
                    $prompt['response_constraints'] = ['minimum_non_whitespace_characters' => 1, 'maximum_characters' => 4000];
                    $prompt['self_check_required'] = true;
                } else {
                    $answer['accepted_normalized'] = self::CLOSED_SHORT_TEXT_VARIANTS[$prompt['code']]
                        ?? throw new RuntimeException("Reviewed closed-response variants are missing for {$prompt['code']}.");
                    $answer['accepted_normalized_provenance_kind'] = 'derived_scoring';
                }
                $counts['short_text_prompts']++;
            } elseif (in_array($form, ['role_play', 'service_artifact'], true)) {
                $prompt['scoring_mode'] = 'model_self_check';
                $answer['scoring_mode'] = 'model_self_check';
                $prompt['response_constraints'] = [
                    'minimum_non_whitespace_characters' => 1,
                    'maximum_characters' => 6000,
                    'audio_storage' => 'disabled',
                ];
                $prompt['self_check_required'] = true;
                $rubric = str_replace('-ACT-ROLEPLAY', '-RP-RUBRIC', (string) $prompt['activity_code']);
                $rubricPath = dirname(dirname($promptPath)).'/rubrics/'.$rubric.'.json';
                if (is_file($rubricPath)) {
                    $prompt['rubric_code'] = $rubric;
                }
                $counts[$form === 'role_play' ? 'role_play_prompts' : 'service_artifact_prompts']++;
            } else {
                throw new RuntimeException("Unsupported source response form {$form} for {$prompt['code']}.");
            }

            $answerText = implode("\n", array_map('strval', $answer['accepted'] ?? []));
            if ($answerText !== '') {
                $answerSource = $compiler->findSourceElement((string) ($answer['accepted'][0] ?? ''), $chapter);
                $answer['source_locator'] = $compiler->sourceTextLocator($answerSource, $answerText, $chapter, $heading);
            }
            $answer['provenance_kind'] = 'source_verbatim';
            $feedbackText = implode("\n", array_map(static fn (array $message): string => (string) $message['text'], $messages));
            if ($feedbackText !== '') {
                $firstFeedback = (string) ($messages[0]['text'] ?? '');
                $feedbackSource = $compiler->findSourceElement($firstFeedback, $chapter);
                $feedback['source_locator'] = $compiler->sourceTextLocator($feedbackSource, $feedbackText, $chapter, $heading);
            }
            $feedback['provenance_kind'] = 'source_verbatim';
            $compiler->writeJson($promptPath, $prompt);
            $compiler->writeJson($answerPath, $answer);
            $compiler->writeJson($feedbackPath, $feedback);
            $counts['exercise_prompts']++;
        }

        self::addRatingItems($compiler, $chapters, $ranges, $counts);
        self::normalizeActivityAggregates($compiler);
        self::validateCounts($counts);

        return $counts;
    }

    private static function isCanonicalBaseline(SourceCompiler $compiler): bool
    {
        $prompts = glob($compiler->outputPath().'/assessment/*/prompts/*.json') ?: [];
        if ($prompts === []) {
            return false;
        }
        foreach ($prompts as $path) {
            $prompt = $compiler->readJson($path);
            if (($prompt['response_form'] ?? null) === 'self_rating'
                || ($prompt['provenance_kind'] ?? null) !== 'source_verbatim'
                || ! is_array($prompt['source_locator'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rebind a normalized package's source locators to the uploaded DOCX while
     * preserving its already-reviewed response and scoring semantics.
     *
     * @param  list<array<string, mixed>>  $chapters
     * @return array<string, int>
     */
    private static function refreshCanonicalBaseline(SourceCompiler $compiler, array $chapters): array
    {
        $root = $compiler->outputPath();
        $counts = [
            'exercise_prompts' => 0,
            'rating_items' => 0,
            'selection_prompts' => 0,
            'ordering_prompts' => 0,
            'short_text_prompts' => 0,
            'role_play_prompts' => 0,
            'service_artifact_prompts' => 0,
            'accepted_strings' => 0,
            'feedback_strings' => 6,
        ];

        foreach (glob($root.'/assessment/*/prompts/*.json') ?: [] as $promptPath) {
            $prompt = $compiler->readJson($promptPath);
            $chapter = (int) substr((string) $prompt['code'], 5, 2);
            $heading = self::headingPath($compiler, $chapters, (string) $prompt['activity_code']);
            $source = $compiler->findSourceElement((string) $prompt['stem'], $chapter);
            $prompt['source_locator'] = $compiler->sourceTextLocator($source, (string) $prompt['stem'], $chapter, $heading);
            $compiler->writeJson($promptPath, $prompt);

            $form = (string) ($prompt['response_form'] ?? '');
            if ($form === 'rating') {
                $counts['rating_items']++;

                continue;
            }
            $counter = match ($form) {
                'selection' => 'selection_prompts',
                'ordering' => 'ordering_prompts',
                'short_text' => 'short_text_prompts',
                'role_play' => 'role_play_prompts',
                'service_artifact' => 'service_artifact_prompts',
                default => throw new RuntimeException("Unsupported canonical response form {$form} for {$prompt['code']}."),
            };
            $counts[$counter]++;
            $counts['exercise_prompts']++;

            $answerPath = self::siblingModelPath($promptPath, 'answers', '-AM');
            $feedbackPath = self::siblingModelPath($promptPath, 'feedback', '-FB');
            $answer = $compiler->readJson($answerPath);
            $feedback = $compiler->readJson($feedbackPath);
            $accepted = array_values(array_filter($answer['accepted'] ?? [], static fn (mixed $value): bool => is_string($value) && $value !== ''));
            $messages = is_array($feedback['messages'] ?? null) ? $feedback['messages'] : [];
            $counts['accepted_strings'] += count($accepted);
            $counts['feedback_strings'] += count($messages);

            if ($accepted !== []) {
                $answerSource = $compiler->findSourceElement($accepted[0], $chapter);
                $answer['source_locator'] = $compiler->sourceTextLocator($answerSource, implode("\n", $accepted), $chapter, $heading);
                $compiler->writeJson($answerPath, $answer);
            }
            if ($messages !== []) {
                $texts = array_values(array_map(static fn (array $message): string => (string) ($message['text'] ?? ''), $messages));
                $feedbackSource = $compiler->findSourceElement($texts[0], $chapter);
                $feedback['source_locator'] = $compiler->sourceTextLocator($feedbackSource, implode("\n", $texts), $chapter, $heading);
                $compiler->writeJson($feedbackPath, $feedback);
            }
        }

        self::normalizeActivityAggregates($compiler);
        self::validateCounts($counts);

        return $counts;
    }

    private static function normalizeActivityAggregates(SourceCompiler $compiler): void
    {
        $root = $compiler->outputPath();
        $byActivity = [];
        foreach (glob($root.'/assessment/*/prompts/*.json') ?: [] as $path) {
            $prompt = $compiler->readJson($path);
            $byActivity[$prompt['activity_code']][] = $prompt;
        }
        foreach (glob($root.'/assessment/*/activities/*.json') ?: [] as $path) {
            $activity = $compiler->readJson($path);
            $prompts = $byActivity[$activity['code']] ?? [];
            if ($prompts === []) {
                throw new RuntimeException("Activity {$activity['code']} has no learner response items.");
            }
            $forms = array_values(array_unique(array_column($prompts, 'response_form')));
            $modes = array_values(array_unique(array_column($prompts, 'scoring_mode')));
            sort($forms, SORT_STRING);
            sort($modes, SORT_STRING);
            $activity['response_form'] = count($forms) === 1 ? $forms[0] : 'mixed';
            $activity['scoring_mode'] = count($modes) === 1 ? $modes[0] : 'mixed';
            $activity['response_forms'] = $forms;
            $activity['prompt_scoring_modes'] = $modes;
            $activity['completion_rule'] ??= in_array('model_self_check', $modes, true)
                ? 'all_required_responses_and_self_checks_completed'
                : 'all_required_items_attempted_and_checked';
            $activity['completion_rule_provenance_kind'] = 'derived_workflow';
            $compiler->writeJson($path, $activity);
        }
    }

    /** @param list<array<string, mixed>> $chapters @param array<string, mixed> $counts */
    private static function addRatingItems(SourceCompiler $compiler, array $chapters, array $ranges, array &$counts): void
    {
        $root = $compiler->outputPath();
        foreach ($chapters as $chapter) {
            $module = (int) $chapter['module'];
            $table = null;
            $section = null;
            $range = null;
            foreach ($chapter['sections'] as $candidate) {
                $candidateRange = $ranges[$candidate['code']];
                foreach ($compiler->elementsBetween($candidateRange['start'], $candidateRange['end']) as $element) {
                    if ($element['kind'] === 'table' && ($element['rows'][0] ?? []) === ['Statement', '1', '2', '3', '4', '5']) {
                        $table = $element;
                        $section = $candidate;
                        $range = $candidateRange;
                        break 2;
                    }
                }
            }
            if ($section === null || $range === null) {
                throw new RuntimeException("Confidence section for chapter {$module} is missing.");
            }
            if ($table === null || count($table['rows']) !== 5) {
                throw new RuntimeException("Confidence table for chapter {$module} is missing or malformed.");
            }

            $chapterCode = (string) $chapter['code'];
            $activityCode = $module === 1 ? $chapterCode.'-ACT-BASELINE' : $chapterCode.'-ACT-CONFIDENCE';
            if ($module === 1) {
                $activityPath = $root.'/assessment/'.$chapterCode.'/activities/'.$activityCode.'.json';
                $guidance = self::baselineGuidance($compiler, $range);
                $compiler->writeJson($activityPath, [
                    'accessibility' => ['keyboard_path', 'non_color_cue', 'no_timing_dependency'],
                    'cefr_activity' => 'self_reflection',
                    'channel' => 'written',
                    'code' => $activityCode,
                    'content_version' => SourceCompiler::VERSION,
                    'entity_type' => 'activity',
                    'guidance' => $guidance['text'],
                    'guidance_provenance_kind' => 'source_verbatim',
                    'id' => $compiler->stableUuid($activityCode),
                    'lesson_code' => $section['code'],
                    'outcome_codes' => $chapter['outcome_codes'],
                    'participation' => 'individual',
                    'pedagogical_function' => 'baseline_reflection',
                    'response_form' => 'rating',
                    'scoring_mode' => 'unscored_self_report',
                    'source_locator' => $compiler->sourceLocatorFor($guidance, $module, [$chapter['title'], $section['title']]),
                    'status' => 'published',
                    'timing' => 'asynchronous',
                    'title' => 'Where you are starting from',
                ]);
            }
            $activityPath = self::activityPath($root, $activityCode);
            $activity = $compiler->readJson($activityPath);
            $activity['response_form'] = 'rating';
            $activity['scoring_mode'] = 'unscored_self_report';
            $activity['rating_item_count'] = 4;
            $activity['completion_rule'] = $module === 1 ? 'all_rated_or_explicitly_skipped' : 'all_rated';
            $activity['completion_rule_provenance_kind'] = 'derived_workflow';
            $compiler->writeJson($activityPath, $activity);

            foreach (array_slice($table['rows'], 1) as $index => $row) {
                $statement = (string) $row[0];
                $suffix = $module === 1 ? 'BASE-R'.($index + 1) : 'CC-R'.($index + 1);
                $code = $chapterCode.'-'.$suffix;
                $path = $root.'/assessment/'.$chapterCode.'/prompts/'.$code.'.json';
                $compiler->writeJson($path, [
                    'activity_code' => $activityCode,
                    'code' => $code,
                    'content_version' => SourceCompiler::VERSION,
                    'entity_type' => 'prompt-item',
                    'id' => $compiler->stableUuid($code),
                    'language' => 'en',
                    'order' => $index + 1,
                    'provenance_kind' => 'source_verbatim',
                    'rating_scale' => ['min' => 1, 'max' => 5, 'values' => [1, 2, 3, 4, 5]],
                    'replaced_by' => null,
                    'replaces' => null,
                    'response_form' => 'rating',
                    'scoring_mode' => 'unscored_self_report',
                    'source_locator' => $compiler->sourceTextLocator($table, $statement, $module, [$chapter['title'], $section['title']]),
                    'status' => 'published',
                    'stem' => $statement,
                ]);
                $counts['rating_items']++;
            }
        }
    }

    private static function baselineGuidance(SourceCompiler $compiler, array $range): array
    {
        foreach ($compiler->elementsBetween($range['start'], $range['end']) as $element) {
            if ($element['kind'] === 'paragraph' && str_starts_with((string) $element['text'], 'Before you begin')) {
                return $element;
            }
        }
        throw new RuntimeException('Chapter 1 baseline guidance is missing.');
    }

    /** @return list<array<string, mixed>> */
    private static function choices(SourceCompiler $compiler, string $promptCode, string $stem, array $messages): array
    {
        $options = self::extractOptions($stem, false);
        $feedbackByLabel = [];
        foreach ($messages as $message) {
            $text = (string) ($message['text'] ?? '');
            if (preg_match('/^\s*(?:\(([A-Ca-c])\)|([A-Ca-c])[.)])\s*/u', $text, $match) === 1) {
                $label = strtolower($match[1] !== '' ? $match[1] : $match[2]);
                $feedbackByLabel[$label][] = [
                    'feedback_type' => (string) ($message['feedback_type'] ?? 'source_feedback'),
                    'text' => $text,
                ];
            }
        }
        if (count($options) < 2) {
            foreach ($feedbackByLabel as $label => $feedback) {
                $source = preg_replace('/^\s*(?:\([A-Ca-c]\)|[A-Ca-c][.)])\s*/u', '', (string) $feedback[0]['text']) ?? '';
                $options[$label] = self::choiceTextFromFeedback($source);
            }
        }
        if (count($options) < 2) {
            throw new RuntimeException("Selection options could not be parsed from source for {$promptCode}.");
        }
        $choices = [];
        foreach ($options as $label => $text) {
            $choices[] = [
                'id' => $compiler->stableUuid($promptCode.'|choice|'.strtolower((string) $label)),
                'label' => strtolower((string) $label),
                'text' => $text,
                'feedback' => $feedbackByLabel[strtolower((string) $label)] ?? [],
            ];
        }

        return $choices;
    }

    /** @return array<string, string> */
    private static function extractOptions(string $text, bool $allowStart = true): array
    {
        $pattern = '/(?:^|(?<=\s))(?:(?:\(([A-Ca-c])\))|(?:([A-Ca-c])[.)]))\s*/u';
        preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
        $options = [];
        for ($index = 0; $index < count($matches[0]); $index++) {
            $label = $matches[1][$index][0] !== '' ? $matches[1][$index][0] : $matches[2][$index][0];
            $start = $matches[0][$index][1] + strlen($matches[0][$index][0]);
            $end = $index + 1 < count($matches[0]) ? $matches[0][$index + 1][1] : strlen($text);
            $value = trim(substr($text, $start, $end - $start));
            $value = preg_replace('/\s+or\s*$/iu', '', $value) ?? $value;
            $value = rtrim($value, " \t\n\r\0\x0B");
            if ($value !== '') {
                $options[strtolower($label)] = $value;
            }
        }

        return $options;
    }

    private static function choiceTextFromFeedback(string $text): string
    {
        if (preg_match('/^[“"](.+?)[”"]/u', $text, $match) === 1) {
            return '“'.$match[1].'”';
        }
        $parts = preg_split('/(?<=\.)\s+/u', $text, 2);

        return trim((string) ($parts[0] ?? $text));
    }

    private static function acceptedChoiceLabel(array $accepted): string
    {
        $value = (string) ($accepted[0] ?? '');
        if (preg_match('/^\s*(?:\(([A-Ca-c])\)|([A-Ca-c]))(?:[.)]|\b)/u', $value, $match) !== 1) {
            throw new RuntimeException("Unable to resolve accepted choice label from: {$value}");
        }

        return strtolower($match[1] !== '' ? $match[1] : $match[2]);
    }

    /** @return array{list<array{id:string,text:string}>,list<string>} */
    private static function ordering(SourceCompiler $compiler, string $promptCode, string $stem, string $accepted): array
    {
        $colon = strrpos($stem, ':');
        if ($colon === false) {
            throw new RuntimeException("Ordering prompt {$promptCode} has no source item delimiter.");
        }
        $sourceItems = array_values(array_filter(array_map(
            static fn (string $item): string => trim($item, " \t\n\r\0\x0B."),
            explode(',', substr($stem, $colon + 1)),
        )));
        if (count($sourceItems) < 2) {
            throw new RuntimeException("Ordering prompt {$promptCode} has fewer than two source items.");
        }
        $tokens = [];
        $positions = [];
        $acceptedLower = mb_strtolower($accepted);
        foreach ($sourceItems as $index => $item) {
            $id = $compiler->stableUuid($promptCode.'|token|'.($index + 1));
            $tokens[] = ['id' => $id, 'text' => $item];
            $needle = mb_strtolower($item);
            $position = mb_strpos($acceptedLower, $needle);
            if ($position === false) {
                $needle = preg_replace('/^(?:the|a|an)\s+/iu', '', $needle) ?? $needle;
                $position = mb_strpos($acceptedLower, $needle);
            }
            if ($position === false) {
                throw new RuntimeException("Correct order for {$promptCode} cannot be mapped to source token: {$item}");
            }
            $positions[$id] = $position;
        }
        asort($positions, SORT_NUMERIC);

        return [$tokens, array_keys($positions)];
    }

    /** @param list<array<string, mixed>> $chapters @return list<string> */
    private static function headingPath(SourceCompiler $compiler, array $chapters, string $activityCode): array
    {
        $activity = $compiler->readJson(self::activityPath($compiler->outputPath(), $activityCode));
        $sectionCode = (string) ($activity['lesson_code'] ?? '');
        foreach ($chapters as $chapter) {
            foreach ($chapter['sections'] as $section) {
                if ($section['code'] === $sectionCode) {
                    return [(string) $chapter['title'], (string) $section['title']];
                }
            }
        }

        return [$activityCode];
    }

    private static function activityPath(string $root, string $activityCode): string
    {
        $chapter = substr($activityCode, 0, 7);
        $path = $root.'/assessment/'.$chapter.'/activities/'.$activityCode.'.json';
        if (! is_file($path)) {
            throw new RuntimeException("Activity file is missing: {$activityCode}");
        }

        return $path;
    }

    private static function siblingModelPath(string $promptPath, string $directory, string $suffix): string
    {
        $base = basename($promptPath, '.json');

        return dirname(dirname($promptPath)).'/'.$directory.'/'.$base.$suffix.'.json';
    }

    private static function unlinkRequired(string $path): void
    {
        if (! is_file($path) || ! unlink($path)) {
            throw new RuntimeException("Unable to remove superseded assessment record: {$path}");
        }
    }

    /** @param array<string, int> $counts */
    private static function validateCounts(array $counts): void
    {
        $expected = [
            'exercise_prompts' => 96,
            'rating_items' => 28,
            'selection_prompts' => 74,
            'ordering_prompts' => 5,
            'short_text_prompts' => 11,
            'role_play_prompts' => 4,
            'service_artifact_prompts' => 2,
            'accepted_strings' => 90,
            'feedback_strings' => 138,
        ];
        foreach ($expected as $name => $value) {
            if (($counts[$name] ?? null) !== $value) {
                throw new RuntimeException("Assessment source count {$name} mismatch: expected {$value}; found ".($counts[$name] ?? 'missing').'.');
            }
        }
    }
}
