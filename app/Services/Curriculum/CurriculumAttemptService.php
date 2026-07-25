<?php

namespace App\Services\Curriculum;

use App\Models\CurriculumActivityProgress;
use App\Models\CurriculumAttempt;
use App\Models\CurriculumDraft;
use App\Models\CurriculumDraftEntity;
use App\Models\CurriculumEntity;
use App\Models\CurriculumResponse;
use App\Models\User;
use App\Services\Engagement\ReviewScheduleService;
use App\Services\LearningContentScope;
use App\Services\LearningContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CurriculumAttemptService
{
    public function __construct(
        private readonly LearningContext $learningContext,
        private readonly LearningContentScope $contentScope,
        private readonly ReviewScheduleService $reviewSchedule,
    ) {}

    public function markViewed(User $user, string $activityCode): void
    {
        $definition = $this->definition($activityCode, $user);
        $context = $this->learningContext->current(request(), $user);
        DB::transaction(function () use ($user, $definition, $context): void {
            $progress = $this->progress($user, $definition, $context);
            if ($progress->viewed_at === null) {
                $progress->forceFill(['viewed_at' => now()])->save();
            }
        }, attempts: 3);
    }

    /** @param array<string, mixed> $validated @return array<string, mixed> */
    public function submit(User $user, string $activityCode, array $validated): array
    {
        $definition = $this->definition($activityCode, $user);
        $context = $this->learningContext->current(request(), $user);
        $applicationKey = (string) config('app.key');
        if ($applicationKey === '') {
            throw new \RuntimeException('APP_KEY is required to protect canonical attempt idempotency fingerprints.');
        }
        $submissionHmacSha256 = hash_hmac('sha256', CanonicalJson::encode([
            'intent' => $validated['intent'],
            'responses' => $validated['responses'] ?? [],
            'self_checks' => $validated['self_checks'] ?? [],
            'rubric_scores' => $validated['rubric_scores'] ?? [],
        ]), $applicationKey);

        return DB::transaction(function () use ($user, $definition, $validated, $submissionHmacSha256, $context): array {
            $package = $definition['package'];
            $activity = $definition['activity'];
            $now = now();
            $identity = [
                'user_id' => $user->id,
                'learning_scope_key' => $context['scope_key'],
                'package_name' => $package->package_name,
                'content_version' => $package->content_version,
                'activity_code' => $activity->code,
                'idempotency_key' => $validated['attempt_key'],
            ];
            $attempt = CurriculumAttempt::query()->firstOrCreate($identity, [
                'institution_membership_id' => $context['membership_id'],
                'course_offering_id' => $context['course_offering_id'],
                'course_enrollment_id' => $context['course_enrollment_id'],
                'activity_source_sha256' => $activity->source_sha256,
                'submission_hmac_sha256' => $submissionHmacSha256,
                'intent' => $validated['intent'],
                'state' => 'started',
                'started_at' => $now,
            ]);
            if (! $attempt->wasRecentlyCreated) {
                $existing = CurriculumAttempt::query()->where($identity)->lockForUpdate()->firstOrFail();
                if (! hash_equals($existing->submission_hmac_sha256, $submissionHmacSha256)) {
                    throw ValidationException::withMessages([
                        'attempt_key' => 'This submission key was already used for different responses. Reload the activity and try again.',
                    ]);
                }

                return $this->result($existing->load('responses'), $definition, reused: true, rubricScores: $validated['rubric_scores'] ?? []);
            }

            $intent = $validated['intent'];
            $attempt->events()->create(['event_type' => 'started', 'occurred_at' => $now]);
            $progress = $this->progress($user, $definition, $context);
            $progress->forceFill([
                'viewed_at' => $progress->viewed_at ?? $now,
                'started_at' => $progress->started_at ?? $now,
            ])->save();

            if (! empty($validated['rubric_scores']) && is_array($validated['rubric_scores'])) {
                $attempt->events()->create(['event_type' => 'rubric_self_assessment', 'occurred_at' => $now]);
            }

            if (! empty($validated['audio']) && is_array($validated['audio'])) {
                foreach ($validated['audio'] as $promptCode => $file) {
                    if ($file instanceof UploadedFile && $file->isValid()) {
                        $extension = strtolower($file->getClientOriginalExtension() ?: 'ogg');
                        $file->storeAs('attempts/'.$attempt->id, $promptCode.'.'.$extension, 'curriculum_private');
                    }
                }
            }

            if ($intent === 'show_model') {
                return $this->result($attempt, $definition, rubricScores: $validated['rubric_scores'] ?? []);
            }
            if ($intent === 'skip_baseline') {
                $attempt->forceFill([
                    'state' => 'completed',
                    'completion_reason' => 'baseline_explicitly_skipped',
                    'completed_at' => $now,
                ])->save();
                $attempt->events()->create(['event_type' => 'completed', 'occurred_at' => $now]);
                $progress->forceFill(['baseline_skipped_at' => $now, 'completed_at' => $now])->save();
                User::forgetAllProgressCaches();

                return $this->result($attempt, $definition, rubricScores: $validated['rubric_scores'] ?? []);
            }

            $selfChecked = false;
            $objectiveResults = [];
            foreach ($definition['prompts'] as $prompt) {
                $code = $prompt->code;
                $form = $prompt->payload['response_form'];
                $mode = $prompt->payload['scoring_mode'];
                $value = $validated['responses'][$code] ?? null;
                $responsePresent = is_array($value) ? $value !== [] : trim((string) $value) !== '';
                $checked = (bool) ($validated['self_checks'][$code] ?? false);
                $isCorrect = $this->score($prompt, $definition['answers'][$code] ?? null, $value);
                if (is_bool($isCorrect)) {
                    $objectiveResults[] = $isCorrect;
                }
                $stored = $this->minimizedResponse($form, $mode, $value);

                $attempt->responses()->create([
                    'prompt_code' => $code,
                    'response_form' => $form,
                    'scoring_mode' => $mode,
                    'response' => $stored,
                    'response_present' => $responsePresent,
                    'is_correct' => $isCorrect,
                    'self_checked' => $checked,
                    'checked_at' => $now,
                ]);
                $selfChecked = $selfChecked || $checked;
            }

            $attempt->forceFill([
                'state' => 'completed',
                'completion_reason' => 'participation_rule_satisfied',
                'attempted_at' => $now,
                'self_checked_at' => $selfChecked ? $now : null,
                'completed_at' => $now,
            ])->save();
            foreach (['attempted', ...($selfChecked ? ['self_checked'] : []), 'completed'] as $event) {
                $attempt->events()->create(['event_type' => $event, 'occurred_at' => $now]);
            }
            $progress->forceFill([
                'attempted_at' => $now,
                'self_checked_at' => $selfChecked ? $now : $progress->self_checked_at,
                'completed_at' => $now,
            ])->save();
            $this->reviewSchedule->schedule(
                $progress,
                CarbonImmutable::instance($now),
                $objectiveResults,
            );
            User::forgetAllProgressCaches();

            return $this->result($attempt->load('responses'), $definition, rubricScores: $validated['rubric_scores'] ?? []);
        }, attempts: 3);
    }

    /**
     * Evaluate a draft with the same scorer/result assembly as active learner
     * delivery, without creating attempts, responses, events, or progress.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function preview(CurriculumDraft $draft, string $activityCode, array $validated): array
    {
        $definition = $this->draftDefinition($draft, $activityCode);
        $intent = $validated['intent'];
        $responses = [];

        if ($intent === 'check') {
            foreach ($definition['prompts'] as $prompt) {
                $value = $validated['responses'][$prompt->code];
                $responses[$prompt->code] = [
                    'response' => $this->minimizedResponse(
                        $prompt->payload['response_form'],
                        $prompt->payload['scoring_mode'],
                        $value,
                    ),
                    'is_correct' => $this->score($prompt, $definition['answers'][$prompt->code] ?? null, $value),
                ];
            }
        }

        return $this->assembleResult(
            intent: $intent,
            state: $intent === 'show_model' ? 'previewed' : 'completed',
            completionReason: $intent === 'skip_baseline' ? 'baseline_explicitly_skipped' : ($intent === 'check' ? 'participation_rule_satisfied' : null),
            responses: $responses,
            definition: $definition,
        );
    }

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function confidenceHistory(User $user): LengthAwarePaginator
    {
        $attempts = CurriculumAttempt::query()
            ->where('user_id', $user->id)
            ->where('learning_scope_key', $this->learningContext->current(request(), $user)['scope_key'])
            ->where('state', 'completed')
            ->whereHas('responses', static fn ($query) => $query->where('response_form', 'rating'))
            ->with(['responses' => static fn ($query) => $query->where('response_form', 'rating')->orderBy('prompt_code')])
            ->orderByDesc('completed_at')
            ->paginate(20);
        $promptCodes = $attempts->getCollection()
            ->flatMap(static fn (CurriculumAttempt $attempt) => $attempt->responses->pluck('prompt_code'))
            ->unique()
            ->values();
        $definitions = CurriculumEntity::query()
            ->select([
                'curriculum_entities.code',
                'curriculum_entities.payload',
                'curriculum_packages.package_name',
                'curriculum_packages.content_version',
            ])
            ->join('curriculum_packages', 'curriculum_packages.id', '=', 'curriculum_entities.curriculum_package_id')
            ->where('curriculum_entities.entity_type', 'prompt-item')
            ->whereIn('curriculum_entities.code', $promptCodes)
            ->get()
            ->keyBy(static fn (CurriculumEntity $prompt): string => $prompt->package_name."\0".$prompt->content_version."\0".$prompt->code);

        return $attempts->through(static fn (CurriculumAttempt $attempt): array => [
            'attempt_id' => $attempt->id,
            'activity_code' => $attempt->activity_code,
            'content_version' => $attempt->content_version,
            'completed_at' => $attempt->completed_at,
            'baseline' => $attempt->activity_code === 'HSP-C01-ACT-BASELINE',
            'ratings' => $attempt->responses->map(static function (CurriculumResponse $response) use ($attempt, $definitions): array {
                $key = $attempt->package_name."\0".$attempt->content_version."\0".$response->prompt_code;
                $definition = $definitions->get($key);

                return [
                    'prompt_code' => $response->prompt_code,
                    'statement' => $definition?->payload['stem'] ?? $response->prompt_code,
                    'rating' => (int) ($response->response['rating'] ?? 0),
                ];
            })->all(),
        ]);
    }

    /** @return array<string, mixed> */
    private function definition(string $activityCode, User $user): array
    {
        $scope = $this->contentScope->current(request(), $user);
        $package = $scope['package'];
        abort_if($package === null, 404);
        $activity = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->id)
            ->where('entity_type', 'activity')
            ->published()
            ->where('code', $activityCode)
            ->first();
        abort_if($activity === null || ! $this->contentScope->allowsEntity($scope, $activity), 404);
        $prompts = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->id)
            ->where('entity_type', 'prompt-item')
            ->published()
            ->where('parent_code', $activity->code)
            ->orderByRaw('position is null')->orderBy('position')->orderBy('code')
            ->get();
        $models = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->id)
            ->whereIn('entity_type', ['answer-model', 'feedback-model'])
            ->whereIn('parent_code', $prompts->pluck('code'))
            ->get()
            ->groupBy('parent_code');
        $answers = [];
        $feedback = [];
        foreach ($prompts as $prompt) {
            $promptModels = $models->get($prompt->code);
            $answers[$prompt->code] = $promptModels?->firstWhere('entity_type', 'answer-model');
            $feedback[$prompt->code] = $promptModels?->firstWhere('entity_type', 'feedback-model');
        }

        return compact('package', 'activity', 'prompts', 'answers', 'feedback');
    }

    /** @return array<string, mixed> */
    private function draftDefinition(CurriculumDraft $draft, string $activityCode): array
    {
        $activity = $draft->entities()->available()
            ->where('entity_type', 'activity')
            ->where('code', $activityCode)
            ->first();
        abort_if($activity === null, 404);
        $prompts = $draft->entities()->available()
            ->where('entity_type', 'prompt-item')
            ->where('parent_code', $activity->code)
            ->orderByRaw('position is null')->orderBy('position')->orderBy('code')
            ->get();
        $models = $draft->entities()->available()
            ->whereIn('entity_type', ['answer-model', 'feedback-model'])
            ->whereIn('parent_code', $prompts->pluck('code'))
            ->get()
            ->groupBy('parent_code');
        $answers = [];
        $feedback = [];
        foreach ($prompts as $prompt) {
            $promptModels = $models->get($prompt->code);
            $answers[$prompt->code] = $promptModels?->firstWhere('entity_type', 'answer-model');
            $feedback[$prompt->code] = $promptModels?->firstWhere('entity_type', 'feedback-model');
        }

        return compact('draft', 'activity', 'prompts', 'answers', 'feedback');
    }

    /** @param array{scope_key: string, membership_id: int|null, course_offering_id: string|null, course_enrollment_id: int|null} $context */
    private function progress(User $user, array $definition, array $context): CurriculumActivityProgress
    {
        $package = $definition['package'];
        $activity = $definition['activity'];
        $progress = CurriculumActivityProgress::query()->firstOrCreate([
            'user_id' => $user->id,
            'learning_scope_key' => $context['scope_key'],
            'package_name' => $package->package_name,
            'content_version' => $package->content_version,
            'activity_code' => $activity->code,
        ], [
            'institution_membership_id' => $context['membership_id'],
            'course_offering_id' => $context['course_offering_id'],
            'course_enrollment_id' => $context['course_enrollment_id'],
            'section_code' => $activity->parent_code,
        ]);

        return CurriculumActivityProgress::query()->whereKey($progress->id)->lockForUpdate()->firstOrFail();
    }

    private function score(CurriculumEntity|CurriculumDraftEntity $prompt, CurriculumEntity|CurriculumDraftEntity|null $answer, mixed $value): ?bool
    {
        $mode = $prompt->payload['scoring_mode'];
        if ($mode === 'objective_choice') {
            return in_array($value, $answer?->payload['correct_choice_ids'] ?? [], true);
        }
        if ($mode === 'objective_ordered') {
            return is_array($value) && $value === ($answer?->payload['correct_order'] ?? []);
        }
        if ($mode === 'objective_normalized_closed') {
            return in_array($this->normalizeClosed((string) $value), $answer?->payload['accepted_normalized'] ?? [], true);
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function minimizedResponse(string $form, string $mode, mixed $value): ?array
    {
        return match (true) {
            $form === 'selection' => ['choice_id' => $value],
            $form === 'ordering' => ['token_ids' => array_values($value)],
            $form === 'rating' => ['rating' => (int) $value],
            $mode === 'objective_normalized_closed' => ['normalized' => $this->normalizeClosed((string) $value)],
            default => null, // Raw open/role-play/writing text is intentionally not persisted.
        };
    }

    private function normalizeClosed(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    /** @return array<string, mixed> */
    private function result(CurriculumAttempt $attempt, array $definition, bool $reused = false, array $rubricScores = []): array
    {
        $storedResponses = $attempt->relationLoaded('responses') ? $attempt->responses->keyBy('prompt_code') : collect();
        $responses = $storedResponses->map(static fn (CurriculumResponse $response): array => [
            'response' => $response->response,
            'is_correct' => $response->is_correct,
        ])->all();

        return $this->assembleResult(
            intent: $attempt->intent,
            state: $attempt->state,
            completionReason: $attempt->completion_reason,
            responses: $responses,
            definition: $definition,
            reused: $reused,
            attemptId: $attempt->id,
            rubricScores: $rubricScores,
        );
    }

    /**
     * @param  array<string, array{response: array<string, mixed>|null, is_correct: bool|null}>  $responses
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function assembleResult(
        string $intent,
        string $state,
        ?string $completionReason,
        array $responses,
        array $definition,
        bool $reused = false,
        int|string|null $attemptId = null,
        array $rubricScores = [],
    ): array {
        $promptResults = [];
        foreach ($definition['prompts'] as $prompt) {
            $answer = $definition['answers'][$prompt->code] ?? null;
            $feedback = $definition['feedback'][$prompt->code] ?? null;
            $response = $responses[$prompt->code] ?? null;
            $messages = array_values($feedback?->payload['messages'] ?? []);
            $selectedChoice = $response['response']['choice_id'] ?? null;
            if (is_string($selectedChoice)) {
                foreach ($prompt->payload['choices'] ?? [] as $choice) {
                    if (($choice['id'] ?? null) === $selectedChoice) {
                        $messages = array_values(array_merge($choice['feedback'] ?? [], $messages));
                        break;
                    }
                }
                $seen = [];
                $messages = array_values(array_filter($messages, static function (array $message) use (&$seen): bool {
                    $key = (string) ($message['feedback_type'] ?? '')."\0".(string) ($message['text'] ?? '');
                    if (isset($seen[$key])) {
                        return false;
                    }
                    $seen[$key] = true;

                    return true;
                }));
            }
            $promptResults[$prompt->code] = [
                'is_correct' => $response['is_correct'] ?? null,
                'model_answers' => array_values($answer?->payload['accepted'] ?? []),
                'feedback' => $messages,
            ];
        }

        return [
            'attempt_id' => $attemptId,
            'intent' => $intent,
            'state' => $state,
            'completion_reason' => $completionReason,
            'reused' => $reused,
            'model_without_attempt' => $intent === 'show_model',
            'prompt_results' => $promptResults,
            'rubric_scores' => $rubricScores,
        ];
    }
}
