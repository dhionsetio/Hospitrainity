<?php

namespace Tests\Feature;

use App\Models\CurriculumActivityProgress;
use App\Models\CurriculumAttempt;
use App\Models\CurriculumEntity;
use App\Models\CurriculumResponse;
use App\Models\User;
use App\Services\Curriculum\CanonicalCurriculumImporter;
use App\Services\Curriculum\CanonicalPackageReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class CanonicalAttemptTest extends TestCase
{
    use RefreshDatabase;

    private string $artifactRoot;

    private User $learner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artifactRoot = storage_path('framework/testing/canonical-attempt-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->artifactRoot);
        File::copy(config('curriculum.standalone_output'), $this->artifactRoot.'/standalone.html');
        config([
            'curriculum.standalone_output' => $this->artifactRoot.'/standalone.html',
            'curriculum.report_directory' => $this->artifactRoot.'/reports',
            'curriculum.rollback_directory' => $this->artifactRoot.'/rollbacks',
        ]);

        app(CanonicalCurriculumImporter::class)->import(app(CanonicalPackageReader::class)->read());
        $this->learner = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->artifactRoot);

        parent::tearDown();
    }

    public function test_first_activity_render_records_and_displays_versioned_viewed_state(): void
    {
        $this->actingAs($this->learner)
            ->get(route('curriculum.activities.show', 'HSP-C02-ACT-QUIZ'))
            ->assertOk()
            ->assertSee('Progress state: viewed');

        $this->assertDatabaseHas('curriculum_activity_progress', [
            'user_id' => $this->learner->id,
            'package_name' => 'hospitrainity',
            'content_version' => '0.4.0-draft',
            'activity_code' => 'HSP-C02-ACT-QUIZ',
        ]);
        $this->assertNotNull(CurriculumActivityProgress::query()->sole()->viewed_at);
    }

    public function test_validation_markup_identifies_prompt_errors_and_links_to_invalid_controls(): void
    {
        $activityCode = 'HSP-C02-ACT-PRACTICE';
        $activityUrl = route('curriculum.activities.show', $activityCode);

        $this->actingAs($this->learner)->get($activityUrl)->assertOk();
        $response = $this->from($activityUrl)
            ->followingRedirects()
            ->post(route('curriculum.activities.attempts.store', $activityCode), [
                'attempt_key' => (string) Str::uuid(),
                'intent' => 'check',
            ]);

        $response
            ->assertOk()
            ->assertSee('data-error-summary', escape: false)
            ->assertSee('data-error-link', escape: false)
            ->assertSee('href="#prompt-HSP-C02-PR-I1"', escape: false)
            ->assertSee('response to prompt HSP-C02-PR-I1')
            ->assertSee('id="prompt-error-HSP-C02-PR-I1"', escape: false)
            ->assertSee('aria-invalid="true"', escape: false)
            ->assertSee('aria-describedby="prompt-error-HSP-C02-PR-I1"', escape: false);

        $this->assertDatabaseCount('curriculum_attempts', 0);
    }

    public function test_objective_attempt_is_server_scored_transactional_and_updates_progress(): void
    {
        $activityCode = 'HSP-C02-ACT-QUIZ';
        $payload = $this->validAttemptPayload($activityCode);

        $this->actingAs($this->learner)
            ->post(route('curriculum.activities.attempts.store', $activityCode), $payload)
            ->assertRedirect(route('curriculum.activities.show', $activityCode).'#attempt-result')
            ->assertSessionHas('attempt_result.state', 'completed');

        $attempt = CurriculumAttempt::query()->with(['responses', 'events'])->sole();
        $this->assertSame('completed', $attempt->state);
        $this->assertSame('participation_rule_satisfied', $attempt->completion_reason);
        $this->assertTrue($attempt->responses->every(static fn (CurriculumResponse $response): bool => $response->is_correct === true));
        $this->assertSame(['attempted', 'completed', 'started'], $attempt->events->pluck('event_type')->sort()->values()->all());
        $this->assertDatabaseHas('curriculum_activity_progress', [
            'user_id' => $this->learner->id,
            'activity_code' => $activityCode,
        ]);
        $this->assertNotNull(CurriculumActivityProgress::query()->sole()->completed_at);
        $this->assertDatabaseCount('completions', 0);
    }

    public function test_attempt_retries_are_idempotent_and_changed_payload_reuse_fails_closed(): void
    {
        $activityCode = 'HSP-C02-ACT-QUIZ';
        $payload = $this->validAttemptPayload($activityCode);

        $this->actingAs($this->learner)->post(route('curriculum.activities.attempts.store', $activityCode), $payload)->assertRedirect();
        $this->post(route('curriculum.activities.attempts.store', $activityCode), $payload)
            ->assertRedirect()
            ->assertSessionHas('attempt_result.reused', true);
        $this->assertDatabaseCount('curriculum_attempts', 1);

        $firstPrompt = array_key_first($payload['responses']);
        $prompt = CurriculumEntity::query()->where('code', $firstPrompt)->sole();
        $currentChoice = $payload['responses'][$firstPrompt];
        $replacement = collect($prompt->payload['choices'])->firstWhere('id', '!=', $currentChoice)['id'];
        $changed = $payload;
        $changed['responses'][$firstPrompt] = $replacement;

        $this->post(route('curriculum.activities.attempts.store', $activityCode), $changed)
            ->assertRedirect()
            ->assertSessionHasErrors('attempt_key');
        $this->assertDatabaseCount('curriculum_attempts', 1);
    }

    public function test_client_cannot_forge_choice_score_nested_keys_or_ordering_tokens(): void
    {
        $quizCode = 'HSP-C02-ACT-QUIZ';
        $payload = $this->validAttemptPayload($quizCode);
        $firstPrompt = array_key_first($payload['responses']);
        $payload['responses'][$firstPrompt] = 'forged-choice-id';
        $payload['score'] = 100;

        $this->actingAs($this->learner)
            ->postJson(route('curriculum.activities.attempts.store', $quizCode), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$firstPrompt === null ? 'responses' : "responses.{$firstPrompt}", 'request']);
        $this->assertDatabaseCount('curriculum_attempts', 0);

        $orderingPrompt = CurriculumEntity::query()
            ->where('entity_type', 'prompt-item')
            ->where('payload->response_form', 'ordering')
            ->firstOrFail();
        $orderingPayload = $this->validAttemptPayload($orderingPrompt->parent_code);
        $orderingPayload['responses'][$orderingPrompt->code] = array_fill(0, count($orderingPrompt->payload['tokens']), 'forged-token');

        $this->postJson(route('curriculum.activities.attempts.store', $orderingPrompt->parent_code), $orderingPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors("responses.{$orderingPrompt->code}.0");
        $this->assertDatabaseCount('curriculum_attempts', 0);
    }

    public function test_show_model_is_explicit_does_not_complete_and_rejects_disguised_responses(): void
    {
        $activityCode = 'HSP-C02-ACT-QUIZ';
        $request = ['attempt_key' => (string) Str::uuid(), 'intent' => 'show_model'];

        $this->actingAs($this->learner)
            ->post(route('curriculum.activities.attempts.store', $activityCode), $request)
            ->assertRedirect()
            ->assertSessionHas('attempt_result.model_without_attempt', true);

        $this->assertDatabaseHas('curriculum_attempts', ['intent' => 'show_model', 'state' => 'started']);
        $this->assertDatabaseCount('curriculum_responses', 0);
        $this->assertNull(CurriculumActivityProgress::query()->sole()->completed_at);

        $invalid = $request;
        $invalid['attempt_key'] = (string) Str::uuid();
        $invalid['responses'] = ['HSP-C02-QZ-Q1' => 'anything'];
        $this->postJson(route('curriculum.activities.attempts.store', $activityCode), $invalid)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('responses');
    }

    public function test_ordering_and_reviewed_closed_text_are_scored_from_server_models(): void
    {
        $orderingPrompt = CurriculumEntity::query()
            ->where('entity_type', 'prompt-item')
            ->where('payload->response_form', 'ordering')
            ->firstOrFail();
        $closedPrompt = CurriculumEntity::query()
            ->where('entity_type', 'prompt-item')
            ->where('payload->scoring_mode', 'objective_normalized_closed')
            ->firstOrFail();
        $activityCodes = collect([$orderingPrompt->parent_code, $closedPrompt->parent_code])->unique();

        $this->actingAs($this->learner);
        $this->get(route('curriculum.activities.show', $orderingPrompt->parent_code))
            ->assertOk()
            ->assertSee('no dragging is required')
            ->assertSee('data-order-up', escape: false)
            ->assertSee('data-ordering-status', escape: false)
            ->assertDontSee('draggable="true"', escape: false);
        foreach ($activityCodes as $activityCode) {
            $this->post(route('curriculum.activities.attempts.store', $activityCode), $this->validAttemptPayload($activityCode))
                ->assertRedirect();
        }

        $orderingResponse = CurriculumResponse::query()->where('prompt_code', $orderingPrompt->code)->sole();
        $this->assertTrue($orderingResponse->is_correct);
        $this->assertSame(
            CurriculumEntity::query()->where('entity_type', 'answer-model')->where('parent_code', $orderingPrompt->code)->sole()->payload['correct_order'],
            $orderingResponse->response['token_ids'],
        );
        $closedResponse = CurriculumResponse::query()->where('prompt_code', $closedPrompt->code)->sole();
        $this->assertTrue($closedResponse->is_correct);
        $this->assertSame(
            CurriculumEntity::query()->where('entity_type', 'answer-model')->where('parent_code', $closedPrompt->code)->sole()->payload['accepted_normalized'][0],
            $closedResponse->response['normalized'],
        );
    }

    public function test_open_production_requires_self_check_and_never_persists_raw_text(): void
    {
        $openPrompt = CurriculumEntity::query()
            ->where('entity_type', 'prompt-item')
            ->where('payload->self_check_required', true)
            ->firstOrFail();
        $activityCode = $openPrompt->parent_code;
        $payload = $this->validAttemptPayload($activityCode);
        $rawText = 'Private rehearsal text '.Str::random(24);
        $payload['responses'][$openPrompt->code] = $rawText;
        unset($payload['self_checks'][$openPrompt->code]);

        $this->actingAs($this->learner)
            ->post(route('curriculum.activities.attempts.store', $activityCode), $payload)
            ->assertRedirect()
            ->assertSessionHasErrors("self_checks.{$openPrompt->code}")
            ->assertSessionHasInput("responses.{$openPrompt->code}", $rawText);
        $this->assertDatabaseCount('curriculum_attempts', 0);

        $payload['self_checks'][$openPrompt->code] = '1';
        $this->post(route('curriculum.activities.attempts.store', $activityCode), $payload)->assertRedirect();

        $stored = CurriculumResponse::query()->where('prompt_code', $openPrompt->code)->sole();
        $this->assertTrue($stored->response_present);
        $this->assertTrue($stored->self_checked);
        $this->assertNull($stored->is_correct);
        $this->assertNull($stored->response);
        $this->assertNotSame($rawText, CurriculumAttempt::query()->sole()->submission_hmac_sha256);
        $this->assertDatabaseHas('curriculum_attempt_events', ['event_type' => 'self_checked']);
        $this->assertFalse(session()->hasOldInput("responses.{$openPrompt->code}"));
    }

    public function test_confidence_and_baseline_apply_their_distinct_completion_rules(): void
    {
        $confidenceCode = 'HSP-C02-ACT-CONFIDENCE';
        $this->actingAs($this->learner)
            ->post(route('curriculum.activities.attempts.store', $confidenceCode), $this->validAttemptPayload($confidenceCode))
            ->assertRedirect();
        $this->assertSame(4, CurriculumResponse::query()->where('response_form', 'rating')->count());
        $this->get(route('curriculum.confidence-history'))
            ->assertOk()
            ->assertSee('My confidence history')
            ->assertSee('not test scores');

        $baselineCode = 'HSP-C01-ACT-BASELINE';
        $this->post(route('curriculum.activities.attempts.store', $baselineCode), [
            'attempt_key' => (string) Str::uuid(),
            'intent' => 'skip_baseline',
        ])->assertRedirect();
        $baseline = CurriculumActivityProgress::query()->where('activity_code', $baselineCode)->sole();
        $this->assertNotNull($baseline->baseline_skipped_at);
        $this->assertNotNull($baseline->completed_at);

        $this->postJson(route('curriculum.activities.attempts.store', 'HSP-C02-ACT-QUIZ'), [
            'attempt_key' => (string) Str::uuid(),
            'intent' => 'skip_baseline',
        ])->assertUnprocessable()->assertJsonValidationErrors('intent');
    }

    public function test_retired_progress_endpoint_cannot_complete_a_canonical_activity(): void
    {
        $activity = CurriculumEntity::query()->where('code', 'HSP-C02-ACT-QUIZ')->sole();

        $this->actingAs($this->learner)
            ->postJson(route('progress.store'), [
                'type' => 'CurriculumActivity',
                'items' => [$activity->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');

        $this->assertDatabaseCount('completions', 0);
        $this->assertDatabaseCount('curriculum_activity_progress', 0);
    }

    /** @return array<string, mixed> */
    private function validAttemptPayload(string $activityCode): array
    {
        $prompts = CurriculumEntity::query()
            ->where('entity_type', 'prompt-item')
            ->where('parent_code', $activityCode)
            ->orderBy('code')
            ->get();
        $answers = CurriculumEntity::query()
            ->where('entity_type', 'answer-model')
            ->whereIn('parent_code', $prompts->pluck('code'))
            ->get()
            ->keyBy('parent_code');
        $responses = [];
        $selfChecks = [];

        foreach ($prompts as $prompt) {
            $payload = $prompt->payload;
            $answer = $answers[$prompt->code] ?? null;
            $responses[$prompt->code] = match ($payload['response_form']) {
                'selection' => $answer->payload['correct_choice_ids'][0],
                'ordering' => $answer->payload['correct_order'],
                'rating' => 3,
                default => ($payload['scoring_mode'] ?? null) === 'objective_normalized_closed'
                    ? $answer->payload['accepted_normalized'][0]
                    : ($answer?->payload['accepted'][0] ?? 'Learner practice response'),
            };
            if (($payload['self_check_required'] ?? false) === true) {
                $selfChecks[$prompt->code] = '1';
            }
        }

        return [
            'attempt_key' => (string) Str::uuid(),
            'intent' => 'check',
            'responses' => $responses,
            'self_checks' => $selfChecks,
        ];
    }
}
