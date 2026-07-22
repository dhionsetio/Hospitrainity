<?php

namespace App\Services\Responses;

use App\Models\CourseOffering;
use App\Models\CurriculumEntity;
use App\Models\LearnerTextResponse;
use App\Models\User;
use App\Services\LearningContentScope;
use App\Services\LearningContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class LearnerTextResponseService
{
    public function __construct(
        private readonly LearningContentScope $contentScope,
        private readonly LearningContext $learningContext,
    ) {}

    /** @return array{activity: CurriculumEntity, prompt: CurriculumEntity, context: array<string, mixed>, course_revision_id: string|null, maximum_characters: int} */
    public function definition(User $user, string $activityCode, string $promptCode): array
    {
        $scope = $this->contentScope->current(request(), $user);
        $package = $scope['package'];
        abort_if($package === null, 404);

        $activity = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->getKey())
            ->where('entity_type', 'activity')
            ->published()
            ->where('code', $activityCode)
            ->first();
        abort_if($activity === null || ! $this->contentScope->allowsEntity($scope, $activity), 404);

        $prompt = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->getKey())
            ->where('entity_type', 'prompt-item')
            ->published()
            ->where('parent_code', $activity->code)
            ->where('code', $promptCode)
            ->first();
        abort_if($prompt === null || ! $this->contentScope->allowsEntity($scope, $prompt), 404);

        $form = (string) ($prompt->payloadData()['response_form'] ?? '');
        abort_unless(in_array($form, config('course_assistant.open_response_forms', []), true), 404);

        $context = $this->learningContext->current(request(), $user);
        $courseRevisionId = null;
        if (is_string($context['course_offering_id'] ?? null)) {
            $courseRevisionId = CourseOffering::query()
                ->whereKey($context['course_offering_id'])
                ->value('course_revision_id');
            if (! is_string($courseRevisionId)) {
                throw new RuntimeException('The selected Class does not have a Course Revision.');
            }
        }

        $configuredMaximum = (int) config('course_assistant.maximum_saved_response_characters', 6000);
        $sourceMaximum = (int) ($prompt->payloadData()['response_constraints']['maximum_characters'] ?? $configuredMaximum);

        return [
            'activity' => $activity,
            'prompt' => $prompt,
            'context' => $context,
            'course_revision_id' => $courseRevisionId,
            'maximum_characters' => max(1, min($configuredMaximum, $sourceMaximum)),
        ];
    }

    /** @param array{response_key: string, kind: string, intent: string, body: string} $input */
    public function save(User $user, string $activityCode, string $promptCode, array $input): LearnerTextResponse
    {
        $definition = $this->definition($user, $activityCode, $promptCode);
        $body = $input['body'];
        if (mb_strlen($body) > $definition['maximum_characters']) {
            throw ValidationException::withMessages([
                'body' => __('responses.validation.body_max', ['max' => $definition['maximum_characters']]),
            ]);
        }

        $activity = $definition['activity'];
        $prompt = $definition['prompt'];
        $context = $definition['context'];
        $activityDigest = (string) $activity->source_sha256;
        $promptDigest = (string) $prompt->source_sha256;
        if (preg_match('/^[0-9a-f]{64}$/', $activityDigest) !== 1
            || preg_match('/^[0-9a-f]{64}$/', $promptDigest) !== 1) {
            throw new RuntimeException('The response source evidence is incomplete.');
        }

        $applicationKey = (string) config('app.key');
        if ($applicationKey === '') {
            throw new RuntimeException('APP_KEY is required to protect saved learner responses.');
        }

        return DB::transaction(function () use (
            $user,
            $input,
            $body,
            $activity,
            $prompt,
            $context,
            $definition,
            $activityDigest,
            $promptDigest,
            $applicationKey,
        ): LearnerTextResponse {
            $response = LearnerTextResponse::query()
                ->where('user_id', $user->getKey())
                ->where('response_key', $input['response_key'])
                ->lockForUpdate()
                ->first();

            if ($response !== null) {
                if ($response->state === 'submitted') {
                    throw ValidationException::withMessages([
                        'body' => __('responses.validation.submitted_immutable'),
                    ]);
                }

                $expected = [
                    'learning_scope_key' => $context['scope_key'],
                    'curriculum_package_id' => (int) $activity->curriculum_package_id,
                    'activity_entity_id' => (int) $activity->getKey(),
                    'prompt_entity_id' => (int) $prompt->getKey(),
                    'kind' => $input['kind'],
                ];
                foreach ($expected as $field => $value) {
                    if ((string) $response->getAttribute($field) !== (string) $value) {
                        throw ValidationException::withMessages([
                            'response_key' => __('responses.validation.key_reused'),
                        ]);
                    }
                }

                $response->forceFill([
                    'body' => $body,
                    'body_hmac_sha256' => hash_hmac('sha256', $body, $applicationKey),
                    'state' => $input['intent'] === 'submit' ? 'submitted' : 'draft',
                    'submitted_at' => $input['intent'] === 'submit' ? now() : null,
                ])->save();

                return $response->refresh();
            }

            return LearnerTextResponse::query()->create([
                'user_id' => $user->getKey(),
                'response_key' => $input['response_key'],
                'learning_scope_key' => $context['scope_key'],
                'institution_membership_id' => $context['membership_id'],
                'course_offering_id' => $context['course_offering_id'],
                'course_enrollment_id' => $context['course_enrollment_id'],
                'course_revision_id' => $definition['course_revision_id'],
                'curriculum_package_id' => $activity->curriculum_package_id,
                'activity_entity_id' => $activity->getKey(),
                'prompt_entity_id' => $prompt->getKey(),
                'activity_source_sha256' => $activityDigest,
                'prompt_source_sha256' => $promptDigest,
                'kind' => $input['kind'],
                'state' => $input['intent'] === 'submit' ? 'submitted' : 'draft',
                'body' => $body,
                'body_hmac_sha256' => hash_hmac('sha256', $body, $applicationKey),
                'submitted_at' => $input['intent'] === 'submit' ? now() : null,
            ]);
        }, attempts: 3);
    }

    public function draft(User $user, string $activityCode, string $promptCode): ?LearnerTextResponse
    {
        $definition = $this->definition($user, $activityCode, $promptCode);

        return LearnerTextResponse::query()
            ->where('user_id', $user->getKey())
            ->where('learning_scope_key', $definition['context']['scope_key'])
            ->where('curriculum_package_id', $definition['activity']->curriculum_package_id)
            ->where('activity_entity_id', $definition['activity']->getKey())
            ->where('prompt_entity_id', $definition['prompt']->getKey())
            ->where('state', 'draft')
            ->latest('updated_at')
            ->first();
    }
}
