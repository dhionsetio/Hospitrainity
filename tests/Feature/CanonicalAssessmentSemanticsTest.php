<?php

namespace Tests\Feature;

use App\Services\Curriculum\CanonicalPackageReader;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CanonicalAssessmentSemanticsTest extends TestCase
{
    public function test_source_exercises_answers_feedback_and_guidance_remain_text_faithful(): void
    {
        $package = app(CanonicalPackageReader::class)->read();
        $entities = collect($package->entities)->keyBy('code');
        $baseline = base_path('curriculum/hospitrainity/0.3.0-draft/assessment');
        $exercisePrompts = 0;
        $acceptedStrings = 0;
        $feedbackStrings = 0;
        $guidance = [];

        foreach (File::glob($baseline.'/*/prompts/*.json') as $path) {
            $source = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
            if ($source['response_form'] === 'self_rating') {
                $guidance[$source['activity_code']] = $source['stem'];

                continue;
            }
            $compiled = $entities[$source['code']];
            $this->assertSame($source['stem'], $compiled['payload']['stem'], $source['code']);
            $exercisePrompts++;

            $answerPath = dirname(dirname($path)).DIRECTORY_SEPARATOR.'answers'.DIRECTORY_SEPARATOR.basename($path, '.json').'-AM.json';
            $sourceAnswer = json_decode(File::get($answerPath), true, flags: JSON_THROW_ON_ERROR);
            $compiledAnswer = $entities[$source['code'].'-AM'];
            $this->assertSame($sourceAnswer['accepted'] ?? [], $compiledAnswer['payload']['accepted'] ?? [], $source['code'].' accepted');
            $acceptedStrings += count(array_filter($sourceAnswer['accepted'] ?? []));

            $feedbackPath = dirname(dirname($path)).DIRECTORY_SEPARATOR.'feedback'.DIRECTORY_SEPARATOR.basename($path, '.json').'-FB.json';
            $sourceFeedback = json_decode(File::get($feedbackPath), true, flags: JSON_THROW_ON_ERROR);
            $compiledFeedback = $entities[$source['code'].'-FB'];
            $this->assertSame($sourceFeedback['messages'], $compiledFeedback['payload']['messages'], $source['code'].' feedback');
            $feedbackStrings += count($sourceFeedback['messages']);
        }

        $this->assertSame(96, $exercisePrompts);
        $this->assertSame(90, $acceptedStrings);
        $this->assertSame(132, $feedbackStrings);
        $this->assertCount(6, $guidance);
        foreach ($guidance as $activityCode => $text) {
            $this->assertSame($text, $entities[$activityCode]['payload']['guidance']);
        }
        $this->assertSame(138, $feedbackStrings + count($guidance));
    }

    public function test_every_response_form_has_structured_non_leaking_semantics(): void
    {
        $package = app(CanonicalPackageReader::class)->read();
        $entities = collect($package->entities);
        $prompts = $entities->where('entity_type', 'prompt-item');
        $answers = $entities->where('entity_type', 'answer-model')->keyBy('parent_code');

        $this->assertCount(124, $prompts);
        $this->assertCount(74, $prompts->where('payload.response_form', 'selection'));
        $this->assertCount(5, $prompts->where('payload.response_form', 'ordering'));
        $this->assertCount(11, $prompts->where('payload.response_form', 'short_text'));
        $this->assertCount(4, $prompts->where('payload.response_form', 'role_play'));
        $this->assertCount(2, $prompts->where('payload.response_form', 'service_artifact'));
        $this->assertCount(28, $prompts->where('payload.response_form', 'rating'));

        foreach ($prompts->where('payload.response_form', 'selection') as $prompt) {
            $choices = $prompt['payload']['choices'];
            $this->assertGreaterThanOrEqual(2, count($choices), $prompt['code']);
            $this->assertSame([], array_filter($choices, static fn (array $choice): bool => Arr::has($choice, 'correct')));
            $this->assertCount(count($choices), array_unique(array_column($choices, 'id')));
            $this->assertContains($answers[$prompt['code']]['payload']['correct_choice_ids'][0], array_column($choices, 'id'));
        }
        foreach ($prompts->where('payload.response_form', 'ordering') as $prompt) {
            $tokenIds = array_column($prompt['payload']['tokens'], 'id');
            $correct = $answers[$prompt['code']]['payload']['correct_order'];
            sort($tokenIds);
            sort($correct);
            $this->assertSame($tokenIds, $correct, $prompt['code']);
        }
        foreach ($prompts->where('payload.response_form', 'rating') as $prompt) {
            $this->assertSame(['max' => 5, 'min' => 1, 'values' => [1, 2, 3, 4, 5]], $prompt['payload']['rating_scale']);
            $this->assertSame('unscored_self_report', $prompt['payload']['scoring_mode']);
            $this->assertFalse($answers->has($prompt['code']));
        }

        $open = $prompts->filter(static fn (array $prompt): bool => in_array($prompt['payload']['response_form'], ['role_play', 'service_artifact'], true)
            || ($prompt['payload']['response_form'] === 'short_text' && $prompt['payload']['scoring_mode'] === 'model_self_check'));
        $this->assertCount(11, $open);
        $this->assertTrue($open->every(static fn (array $prompt): bool => $prompt['payload']['self_check_required'] === true
            && ($prompt['payload']['response_form'] === 'short_text'
                || ($prompt['payload']['response_constraints']['audio_storage'] ?? null) === 'disabled')));

        $this->assertTrue($entities->where('entity_type', 'rubric')->every(
            static fn (array $rubric): bool => $rubric['payload']['provenance_kind'] === 'derived_review'
                && $rubric['payload']['review_status'] === 'provisional',
        ));
        $framework = json_decode(File::get(config('curriculum.package_path').'/framework/outcome-alignments.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('derived_review', $framework['provenance_kind']);
        $this->assertSame('provisional', $framework['review_status']);
    }
}
