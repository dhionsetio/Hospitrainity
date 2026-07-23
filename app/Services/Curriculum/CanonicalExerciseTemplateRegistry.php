<?php

namespace App\Services\Curriculum;

use InvalidArgumentException;

final class CanonicalExerciseTemplateRegistry
{
    public const VERSION = '1.0.0';

    /**
     * The legacy names are retained only as authoring presets. Every enabled
     * preset compiles to the canonical response/scoring contract below.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return [
            'spelling_quiz' => $this->definition('closed', 'short_text', 'objective_normalized_closed', 1, 20, audio: true, enabled: false, unavailableReason: 'audio_equivalent_not_approved'),
            'matching_game' => $this->definition('derived_selection', 'selection', 'objective_choice', 2, 12),
            'fill_in_the_blank' => $this->definition('closed', 'short_text', 'objective_normalized_closed', 1, 20),
            'listening_task' => $this->definition('explicit_selection', 'selection', 'objective_choice', 1, 12, audio: true, enabled: false, unavailableReason: 'audio_equivalent_not_approved'),
            'speaking_practice' => $this->definition('open', 'role_play', 'rubric_self_assessment', 1, 10, rubric: true),
            'sentence_scramble' => $this->definition('ordering', 'ordering', 'objective_ordered', 1, 10),
            'translation_match' => $this->definition('derived_selection', 'selection', 'objective_choice', 2, 12),
            'fill_with_options' => $this->definition('explicit_selection', 'selection', 'objective_choice', 1, 20),
            'multiple_choice_quiz' => $this->definition('explicit_selection', 'selection', 'objective_choice', 1, 20),
            'fill_multiple_blanks' => $this->definition('closed', 'short_text', 'objective_normalized_closed', 2, 20),
            'silent_letter_hunt' => $this->definition('silent_letter', 'selection', 'objective_choice', 1, 20),
            'pronunciation_drill' => $this->definition('open', 'role_play', 'model_self_check', 1, 20),
            'sound_sorting' => $this->definition('derived_selection', 'selection', 'objective_choice', 2, 20),
            'sequencing' => $this->definition('ordering', 'ordering', 'objective_ordered', 1, 10),
            'information' => $this->definition('open', 'short_text', 'unscored_self_report', 1, 1),
            'writing' => $this->definition('open', 'short_text', 'rubric_self_assessment', 1, 10, rubric: true),
            'drag_the_words' => $this->definition('ordering', 'ordering', 'objective_ordered', 1, 20),
            'drag_and_drop' => $this->definition('explicit_selection', 'selection', 'objective_choice', 1, 50),
        ];
    }

    /** @return array<string, mixed> */
    public function get(string $type): array
    {
        return $this->all()[$type] ?? throw new InvalidArgumentException("Unknown canonical exercise template: {$type}.");
    }

    public function has(string $type): bool
    {
        return isset($this->all()[$type]);
    }

    /** @return list<string> */
    public function enabledTypes(): array
    {
        return array_keys(array_filter($this->all(), static fn (array $definition): bool => $definition['enabled'] === true));
    }

    /** @return array<string, array<string, mixed>> */
    public function scoringPolicies(): array
    {
        return [
            'objective_choice' => ['policy' => 'exact', 'server_scored' => true, 'persists' => 'choice_id'],
            'objective_normalized_closed' => ['policy' => 'normalized_closed_response', 'server_scored' => true, 'persists' => 'normalized_response'],
            'objective_ordered' => ['policy' => 'ordered_response', 'server_scored' => true, 'persists' => 'token_ids'],
            'model_self_check' => ['policy' => 'model_self_check', 'server_scored' => false, 'persists' => 'completion_metadata_only'],
            'rubric_self_assessment' => ['policy' => 'rubric_self_assessment', 'server_scored' => false, 'persists' => 'completion_metadata_only'],
            'unscored_self_report' => ['policy' => 'unscored_confidence', 'server_scored' => false, 'persists' => 'rating'],
        ];
    }

    /** @return list<string> */
    public function lines(?string $value, int $maximum = 20): array
    {
        $lines = preg_split('/\R/u', (string) $value) ?: [];
        $lines = array_values(array_filter(array_map(static fn (string $line): string => trim($line), $lines), static fn (string $line): bool => $line !== ''));

        return array_slice($lines, 0, $maximum);
    }

    /**
     * Perform the cross-field checks that ordinary nested field rules cannot
     * express. The compiler repeats the identity/reference checks when it
     * materializes canonical entities.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array<string, list<string>>
     */
    public function semanticErrors(string $type, array $items, ?string $rubric): array
    {
        if (! $this->has($type) || $this->get($type)['enabled'] !== true) {
            return ['template_type' => ['The selected exercise template is unavailable.']];
        }
        $definition = $this->get($type);
        $errors = [];
        $count = count($items);
        if ($count < $definition['cardinality']['minimum'] || $count > $definition['cardinality']['maximum']) {
            $errors['items'][] = sprintf(
                'This template requires between %d and %d non-empty items.',
                $definition['cardinality']['minimum'],
                $definition['cardinality']['maximum'],
            );
        }

        foreach ($items as $index => $item) {
            $prefix = 'items.'.$index;
            $stem = trim((string) ($item['stem'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));
            $feedback = trim((string) ($item['feedback'] ?? ''));
            if ($stem === '') {
                $errors[$prefix.'.stem'][] = 'Each item needs an accessible prompt.';
            }
            if ($feedback === '') {
                $errors[$prefix.'.feedback'][] = 'Each item needs feedback or a next-step message.';
            }

            if ($definition['editor'] === 'closed') {
                $answers = $this->lines($answer, 8);
                if ($answers === []) {
                    $errors[$prefix.'.answer'][] = 'Provide at least one accepted closed answer.';
                }
                if (count($answers) !== count(array_unique(array_map($this->normalize(...), $answers)))) {
                    $errors[$prefix.'.answer'][] = 'Accepted answers must be unique after normalization.';
                }
            } elseif ($definition['editor'] === 'explicit_selection') {
                $options = $this->lines((string) ($item['options'] ?? ''), 10);
                $normalizedOptions = array_map($this->normalize(...), $options);
                if (count($options) < 2) {
                    $errors[$prefix.'.options'][] = 'Provide at least two options.';
                } elseif (count($options) !== count(array_unique($normalizedOptions))) {
                    $errors[$prefix.'.options'][] = 'Options must be unique after normalization.';
                }
                if ($answer === '' || ! in_array($this->normalize($answer), $normalizedOptions, true)) {
                    $errors[$prefix.'.answer'][] = 'The correct answer must match one of the submitted options.';
                }
            } elseif ($definition['editor'] === 'ordering') {
                $tokens = $this->lines((string) ($item['tokens'] ?? ''), 12);
                $normalizedTokens = array_map($this->normalize(...), $tokens);
                if (count($tokens) < 2) {
                    $errors[$prefix.'.tokens'][] = 'Provide at least two ordered items.';
                } elseif (count($tokens) !== count(array_unique($normalizedTokens))) {
                    $errors[$prefix.'.tokens'][] = 'Ordered items must be unique after normalization.';
                }
            } elseif ($definition['editor'] === 'open') {
                if (trim((string) ($item['model_answer'] ?? '')) === '') {
                    $errors[$prefix.'.model_answer'][] = 'Provide a model for learner self-checking; it will not be auto-graded.';
                }
            } elseif ($definition['editor'] === 'silent_letter') {
                $letters = $this->graphemes($stem);
                if (count($letters) < 2) {
                    $errors[$prefix.'.stem'][] = 'Provide a word containing at least two distinct letters.';
                }
                if ($answer === '' || ! in_array(mb_strtolower($answer), $letters, true)) {
                    $errors[$prefix.'.answer'][] = 'The silent-letter answer must be one letter that occurs in the word.';
                }
            }
        }

        if ($definition['editor'] === 'derived_selection') {
            $answers = array_map(fn (array $item): string => $this->normalize((string) ($item['answer'] ?? '')), $items);
            if (in_array('', $answers, true)) {
                $errors['items'][] = 'Every matching or sorting item needs an answer/category.';
            }
            if (count(array_unique($answers)) < 2) {
                $errors['items'][] = 'Matching and sorting templates need at least two distinct answers/categories.';
            }
        }

        if ($definition['rubric_required'] && $this->rubricRows($rubric) === []) {
            $errors['rubric'][] = 'This open-language template requires at least one rubric row.';
        }

        return $errors;
    }

    /** @return list<array{descriptor: string, levels: list<string>}> */
    public function rubricRows(?string $rubric): array
    {
        $rows = [];
        foreach ($this->lines($rubric, 8) as $line) {
            $parts = array_values(array_filter(array_map('trim', explode('|', $line)), static fn (string $part): bool => $part !== ''));
            if (count($parts) >= 3) {
                $rows[] = ['descriptor' => array_shift($parts), 'levels' => array_slice($parts, 0, 5)];
            }
        }

        return $rows;
    }

    /** @return list<string> */
    public function graphemes(string $word): array
    {
        preg_match_all('/\p{L}/u', mb_strtolower($word), $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    /** @return array<string, mixed> */
    private function definition(
        string $editor,
        string $responseForm,
        string $scoringMode,
        int $minimum,
        int $maximum,
        bool $audio = false,
        bool $rubric = false,
        bool $enabled = true,
        ?string $unavailableReason = null,
    ): array {
        return [
            'enabled' => $enabled,
            'unavailable_reason' => $unavailableReason,
            'registry_version' => self::VERSION,
            'editor' => $editor,
            'response_form' => $responseForm,
            'scoring_mode' => $scoringMode,
            'cardinality' => ['minimum' => $minimum, 'maximum' => $maximum],
            'audio_required' => $audio,
            'rubric_required' => $rubric,
            'stable_identifiers' => ['prompt_code', 'choice_id', 'token_id'],
            'answer_reference' => match ($responseForm) {
                'selection' => 'correct_choice_ids',
                'ordering' => 'correct_order',
                'short_text' => 'accepted_normalized',
                default => 'accepted_model_only',
            },
            'accessibility' => array_values(array_filter([
                'keyboard_path', 'non_color_cue', 'no_timing_dependency', $audio ? 'text_alt' : null,
            ])),
            'audio_accessibility' => $audio ? [
                'native_keyboard_controls', 'accessible_description', 'repeat_without_timing_penalty',
            ] : [],
            'renderer' => 'canonical_activity',
        ];
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
