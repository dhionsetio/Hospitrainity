<?php

namespace App\Services;

use App\Enums\InstitutionMembershipStatus;
use App\Models\Completion;
use App\Models\CurriculumActivityProgress;
use App\Models\CurriculumAttempt;
use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\User;
use Illuminate\Support\Collection;

class CanonicalCurriculumRepository
{
    public function __construct(
        private readonly LearningContext $learningContext,
        private readonly LearningContentScope $contentScope,
        private readonly CurriculumStepPlanner $steps,
    ) {}

    public function activePackage(): ?CurriculumPackage
    {
        return CurriculumPackage::active();
    }

    public function isActive(): bool
    {
        return $this->activePackage() !== null;
    }

    public function packageFor(User $user): ?CurriculumPackage
    {
        return $this->contentScope->current(request(), $user)['package'];
    }

    public function isActiveFor(User $user): bool
    {
        return $this->packageFor($user) !== null;
    }

    /** @return Collection<int, array<string, mixed>> */
    public function dashboardChaptersFor(User $user): Collection
    {
        $scope = $this->contentScope->current(request(), $user);
        $package = $scope['package'];
        if ($package === null) {
            return collect();
        }

        $entities = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->id)
            ->whereIn('entity_type', ['chapter', 'lesson-section', 'activity'])
            ->published()
            ->get();

        if ($scope['allowed_chapter_codes'] !== null) {
            $allowedChapterCodes = $scope['allowed_chapter_codes'];
            $entities = $entities->filter(static function (CurriculumEntity $entity) use ($allowedChapterCodes, $entities): bool {
                return match ($entity->entity_type) {
                    'chapter' => in_array($entity->code, $allowedChapterCodes, true),
                    'lesson-section' => in_array($entity->parent_code, $allowedChapterCodes, true),
                    'activity' => $entities->contains(static fn (CurriculumEntity $section): bool => $section->entity_type === 'lesson-section'
                        && $section->code === $entity->parent_code
                        && in_array($section->parent_code, $allowedChapterCodes, true)
                    ),
                    default => false,
                };
            });
        }

        $sections = $entities->where('entity_type', 'lesson-section')->groupBy('parent_code');
        $sectionChapter = $entities->where('entity_type', 'lesson-section')->pluck('parent_code', 'code');
        $activities = $entities->where('entity_type', 'activity');
        $activitiesByChapter = $activities->groupBy(fn (CurriculumEntity $activity): ?string => $sectionChapter[$activity->parent_code] ?? null);
        $completedActivityCodes = CurriculumActivityProgress::query()
            ->where('user_id', $user->id)
            ->where('learning_scope_key', $this->scopeKey($user))
            ->where('package_name', $package->package_name)
            ->where('content_version', $package->content_version)
            ->whereNotNull('completed_at')
            ->pluck('activity_code')
            ->flip();

        return $entities->where('entity_type', 'chapter')
            ->sortBy(static fn (CurriculumEntity $chapter): int => (int) $chapter->payloadData()['module'])
            ->values()
            ->map(function (CurriculumEntity $chapter) use ($sections, $activitiesByChapter, $completedActivityCodes): array {
                $chapterActivities = $activitiesByChapter[$chapter->code] ?? collect();
                $chapterPayload = $chapter->payloadData();
                $completedCount = $chapterActivities->filter(
                    static fn (CurriculumEntity $activity): bool => isset($completedActivityCodes[$activity->code]),
                )->count();
                $activityCount = $chapterActivities->count();

                return [
                    'code' => $chapter->code,
                    'title' => $chapterPayload['title'],
                    'module' => (int) $chapterPayload['module'],
                    'status' => $chapter->lifecycle_status,
                    'sections_count' => ($sections[$chapter->code] ?? collect())->count(),
                    'activities_count' => $activityCount,
                    'progress' => $activityCount === 0 ? 0 : (int) round(($completedCount / $activityCount) * 100),
                    'completed_activities' => $completedCount,
                ];
            });
    }

    /** @return array<string, mixed>|null */
    public function chapter(string $code, ?User $user = null): ?array
    {
        $scope = $user === null ? null : $this->contentScope->current(request(), $user);
        $package = $scope['package'] ?? $this->activePackage();
        if ($package === null) {
            return null;
        }

        $chapter = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->id)
            ->where('entity_type', 'chapter')
            ->published()
            ->where('code', $code)
            ->first();
        if ($chapter === null || ($scope !== null && ! $this->contentScope->allowsEntity($scope, $chapter))) {
            return null;
        }

        $sections = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->id)
            ->where('entity_type', 'lesson-section')
            ->published()
            ->where('parent_code', $chapter->code)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        $activities = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->id)
            ->where('entity_type', 'activity')
            ->published()
            ->whereIn('parent_code', $sections->pluck('code'))
            ->get()
            ->keyBy('parent_code');
        $outcomes = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->id)
            ->where('entity_type', 'outcome')
            ->whereIn('code', $chapter->payload['outcome_codes'] ?? [])
            ->get()
            ->keyBy('code');

        $sectionModels = $sections->map(function (CurriculumEntity $section) use ($activities): array {
            $activity = $activities[$section->code] ?? null;

            return [
                'code' => $section->code,
                'title' => $this->sanitizeSectionTitle($section->payload['title']),
                'order' => (int) $section->position,
                'status' => $section->lifecycle_status,
                'source_locator' => $section->payload['source_locator'] ?? null,
                'activity' => $activity === null ? null : [
                    'code' => $activity->code,
                    'title' => $activity->payload['title'],
                    'response_form' => $activity->payload['response_form'],
                    'scoring_mode' => $activity->payload['scoring_mode'],
                ],
            ];
        })->values();

        return [
            'code' => $chapter->code,
            'title' => $chapter->payload['title'],
            'module' => (int) $chapter->payload['module'],
            'status' => $chapter->lifecycle_status,
            'source_locator' => $chapter->payload['source_locator'] ?? null,
            'outcomes' => collect($chapter->payload['outcome_codes'] ?? [])->map(
                static fn (string $outcomeCode): ?array => isset($outcomes[$outcomeCode])
                    ? array_merge(['code' => $outcomeCode], $outcomes[$outcomeCode]->payload)
                    : null,
            )->filter()->values(),
            'sections' => $sectionModels,
            'steps' => $this->steps->group($sectionModels),
            'package' => $package,
        ];
    }

    /** @return array<string, mixed>|null */
    public function section(string $code, ?User $user = null): ?array
    {
        $scope = $user === null ? null : $this->contentScope->current(request(), $user);
        $package = $scope['package'] ?? $this->activePackage();
        if ($package === null) {
            return null;
        }

        $section = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->id)
            ->where('entity_type', 'lesson-section')
            ->published()
            ->where('code', $code)
            ->first();
        if ($section === null || ($scope !== null && ! $this->contentScope->allowsEntity($scope, $section))) {
            return null;
        }
        $chapter = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->id)
            ->where('entity_type', 'chapter')
            ->published()
            ->where('code', $section->parent_code)
            ->firstOrFail();
        $activity = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->id)
            ->where('entity_type', 'activity')
            ->published()
            ->where('parent_code', $section->code)
            ->first();

        $chapters = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->id)
            ->where('entity_type', 'chapter')
            ->published()
            ->get()
            ->when($scope !== null && $scope['allowed_chapter_codes'] !== null, static fn (Collection $chapters): Collection => $chapters->whereIn('code', $scope['allowed_chapter_codes'])
            )
            ->sortBy(static fn (CurriculumEntity $item): int => (int) $item->payload['module'])
            ->values();
        $chapterOrder = $chapters->pluck('payload.module', 'code');
        $sequence = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->id)
            ->where('entity_type', 'lesson-section')
            ->published()
            ->get()
            ->when($scope !== null && $scope['allowed_chapter_codes'] !== null, static fn (Collection $sections): Collection => $sections->whereIn('parent_code', $scope['allowed_chapter_codes'])
            )
            ->sortBy(static fn (CurriculumEntity $item): string => sprintf(
                '%03d-%03d',
                (int) ($chapterOrder[$item->parent_code] ?? PHP_INT_MAX),
                (int) $item->position,
            ))
            ->values();
        $current = $sequence->search(static fn (CurriculumEntity $item): bool => $item->id === $section->id);
        if ($current === false) {
            return null;
        }
        $navigationItem = function (?CurriculumEntity $item): ?array {
            return $item === null ? null : [
                'code' => $item->code,
                'title' => $this->sanitizeSectionTitle($item->payload['title']),
            ];
        };
        $chapterSections = $sequence
            ->filter(static fn (CurriculumEntity $item): bool => $item->parent_code === $chapter->code)
            ->values()
            ->map(function (CurriculumEntity $item): array {
                return [
                    'code' => $item->code,
                    'title' => $this->sanitizeSectionTitle($item->payload['title']),
                    'order' => (int) $item->position,
                ];
            });
        $step = $this->steps->context($chapterSections, $section->code);
        if ($step === null) {
            return null;
        }
        $blocks = array_map(function (array $block): array {
            $path = $block['asset']['path'] ?? null;
            if (is_string($path) && preg_match('#^assets/([0-9a-f]{64})\.(jpg|jpeg|png|webp|mp3|wav)$#', $path, $matches) === 1) {
                $block['asset']['url'] = route('curriculum.assets.show', [$matches[1], $matches[2]]);
            }

            return $block;
        }, array_values($section->payload['blocks'] ?? []));

        $isWarmUp = (int) $section->position === 1 || str_contains(strtolower($section->payload['title']), 'warm-up');

        $activityDetails = null;
        if ($activity !== null) {
            $promptCount = CurriculumEntity::query()
                ->where('curriculum_package_id', $package->id)
                ->where('entity_type', 'prompt-item')
                ->where('parent_code', $activity->code)
                ->count();
            $unitCount = max(1, $promptCount);
            $responseForm = $activity->payload['response_form'] ?? 'explicit_selection';
            $formLabels = [
                'explicit_selection' => 'Question Set',
                'short_text' => 'Fill in the Blank',
                'ordering' => 'Sequence Ordering',
                'role_play' => 'Speaking Practice',
            ];
            $typeLabel = $formLabels[$responseForm] ?? ucfirst(str_replace('_', ' ', $responseForm));
            $summaryText = $activity->payload['guidance'] ?? __('Practice key concepts covered in this lesson.');

            $activityDetails = [
                'code' => $activity->code,
                'title' => $activity->payload['title'],
                'response_form' => $responseForm,
                'type_label' => $typeLabel,
                'summary' => $summaryText,
                'unit_count' => $unitCount,
                'estimated_minutes' => max(2, (int) ceil($unitCount * 1.5)),
            ];
        }

        return [
            'code' => $section->code,
            'title' => $this->sanitizeSectionTitle($section->payload['title']),
            'order' => (int) $section->position,
            'is_warm_up' => $isWarmUp,
            'status' => $section->lifecycle_status,
            'blocks' => $blocks,
            'chapter' => [
                'code' => $chapter->code,
                'title' => $chapter->payload['title'],
                'module' => (int) $chapter->payload['module'],
            ],
            'activity' => $activityDetails,
            'navigation' => [
                'position' => $current + 1,
                'total' => $sequence->count(),
                'previous' => $navigationItem($current > 0 ? $sequence[$current - 1] : null),
                'next' => $navigationItem($current + 1 < $sequence->count() ? $sequence[$current + 1] : null),
            ],
            'step' => $step,
            'package' => $package,
        ];
    }

    /** @return array<string, mixed>|null */
    public function activity(string $code, User $user): ?array
    {
        $scope = $this->contentScope->current(request(), $user);
        $package = $scope['package'];
        if ($package === null) {
            return null;
        }

        $activity = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->id)
            ->where('entity_type', 'activity')
            ->published()
            ->where('code', $code)
            ->first();
        if ($activity === null || ! $this->contentScope->allowsEntity($scope, $activity)) {
            return null;
        }

        $section = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->id)
            ->where('entity_type', 'lesson-section')
            ->where('code', $activity->parent_code)
            ->firstOrFail();
        $chapter = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->id)
            ->where('entity_type', 'chapter')
            ->where('code', $section->parent_code)
            ->firstOrFail();
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
        $rubric = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->id)
            ->where('entity_type', 'rubric')
            ->where('parent_code', $activity->code)
            ->first();
        $outcomes = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->id)
            ->where('entity_type', 'outcome')
            ->whereIn('code', $activity->payload['outcome_codes'] ?? [])
            ->get()
            ->keyBy('code');

        $progress = CurriculumActivityProgress::query()
            ->where('user_id', $user->id)
            ->where('learning_scope_key', $this->scopeKey($user))
            ->where('package_name', $package->package_name)
            ->where('content_version', $package->content_version)
            ->where('activity_code', $activity->code)
            ->first();
        $legacyCompleted = Completion::query()
            ->where('user_id', $user->id)
            ->where('learning_scope_key', $this->scopeKey($user))
            ->where('completable_type', CurriculumEntity::class)
            ->where('completable_id', $activity->id)
            ->exists();
        $legacyMapped = CurriculumActivityProgress::query()
            ->where('user_id', $user->id)
            ->where('learning_scope_key', $this->scopeKey($user))
            ->where('package_name', $package->package_name)
            ->where('activity_code', $activity->code)
            ->where('legacy_status', 'legacy_reveal_only')
            ->exists();

        return [
            'id' => (int) $activity->id,
            'code' => $activity->code,
            'title' => $activity->payload['title'],
            'status' => $activity->lifecycle_status,
            'metadata' => collect($activity->payload)->only([
                'accessibility', 'cefr_activity', 'channel', 'participation', 'pedagogical_function',
                'response_form', 'scoring_mode', 'timing', 'source_locator',
            ])->all(),
            'guidance' => $activity->payload['guidance'] ?? null,
            'completion_rule' => $activity->payload['completion_rule'] ?? null,
            'chapter' => ['code' => $chapter->code, 'title' => $chapter->payload['title'], 'module' => (int) $chapter->payload['module']],
            'section' => ['code' => $section->code, 'title' => $section->payloadData()['title']],
            'prompts' => $prompts->map(static function (CurriculumEntity $prompt) use ($models): array {
                $promptModels = $models[$prompt->code] ?? collect();
                $answer = $promptModels->firstWhere('entity_type', 'answer-model');
                $promptPayload = $prompt->payloadData();

                $audio = $promptPayload['audio_asset'] ?? null;
                if (is_array($audio) && is_string($audio['path'] ?? null) && preg_match('#^assets/([0-9a-f]{64})\.(mp3|wav)$#', $audio['path'], $matches) === 1) {
                    $audio['url'] = route('curriculum.assets.show', [$matches[1], $matches[2]]);
                }

                return [
                    'code' => $prompt->code,
                    'stem' => $promptPayload['stem'],
                    'response_form' => $promptPayload['response_form'],
                    'choices' => array_map(static fn (array $choice): array => [
                        'id' => $choice['id'],
                        'label' => $choice['label'],
                        'text' => $choice['text'],
                    ], array_values($promptPayload['choices'] ?? [])),
                    'rating_scale' => $promptPayload['rating_scale'] ?? null,
                    'response_constraints' => $promptPayload['response_constraints'] ?? null,
                    'scoring_mode' => $promptPayload['scoring_mode'] ?? $answer?->payload['scoring_mode'] ?? null,
                    'self_check_required' => (bool) ($promptPayload['self_check_required'] ?? false),
                    'source_locator' => $promptPayload['source_locator'] ?? null,
                    'tokens' => array_values($promptPayload['tokens'] ?? []),
                    'audio' => $audio,
                ];
            })->values(),
            'rubric' => $rubric?->payload,
            'outcomes' => collect($activity->payload['outcome_codes'] ?? [])->map(
                static fn (string $outcomeCode): ?array => isset($outcomes[$outcomeCode])
                    ? array_merge(['code' => $outcomeCode], $outcomes[$outcomeCode]->payload)
                    : null,
            )->filter()->values(),
            'progress' => [
                'state' => match (true) {
                    $progress?->completed_at !== null => 'completed',
                    $progress?->self_checked_at !== null => 'self_checked',
                    $progress?->attempted_at !== null => 'attempted',
                    $progress?->started_at !== null => 'started',
                    $progress?->viewed_at !== null => 'viewed',
                    default => 'not_started',
                },
                'completed' => $progress?->completed_at !== null,
                'legacy_reveal_only' => $legacyCompleted || $legacyMapped || $progress?->legacy_status === 'legacy_reveal_only',
                'attempt_count' => CurriculumAttempt::query()
                    ->where('user_id', $user->id)
                    ->where('learning_scope_key', $this->scopeKey($user))
                    ->where('package_name', $package->package_name)
                    ->where('content_version', $package->content_version)
                    ->where('activity_code', $activity->code)
                    ->count(),
            ],
            'completed' => $progress?->completed_at !== null,
            'package' => $package,
        ];
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array<int|string, int>
     */
    public function overallForUsers(Collection $users): array
    {
        $package = $this->activePackage();
        if ($package === null || $users->isEmpty()) {
            return [];
        }

        $activityCount = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->id)
            ->where('entity_type', 'activity')
            ->published()
            ->count();
        if ($activityCount === 0) {
            return $users->mapWithKeys(static fn (User $user): array => [$user->getKey() => 0])->all();
        }

        $completedCounts = CurriculumActivityProgress::query()
            ->join('curriculum_entities as active_activity', function ($join) use ($package): void {
                $join->on('active_activity.code', '=', 'curriculum_activity_progress.activity_code')
                    ->where('active_activity.curriculum_package_id', '=', $package->id)
                    ->where('active_activity.entity_type', '=', 'activity')
                    ->where('active_activity.lifecycle_status', '=', 'published');
            })
            ->whereIn('curriculum_activity_progress.user_id', $users->map(static fn (User $user) => $user->getKey())->all())
            ->where('curriculum_activity_progress.package_name', $package->package_name)
            ->where('curriculum_activity_progress.content_version', $package->content_version)
            ->whereNotNull('curriculum_activity_progress.completed_at')
            ->selectRaw('curriculum_activity_progress.user_id as progress_user_id, COUNT(*) as completed_count')
            ->groupBy('curriculum_activity_progress.user_id')
            ->pluck('completed_count', 'progress_user_id');

        return $users->mapWithKeys(static function (User $user) use ($completedCounts, $activityCount): array {
            $count = (int) ($completedCounts[$user->getKey()] ?? 0);

            return [$user->getKey() => (int) round(($count / $activityCount) * 100)];
        })->all();
    }

    /**
     * Return only progress explicitly attributed to the selected institution.
     * Personal and other-institution rows are excluded even for the same user.
     *
     * @param  Collection<int, User>  $users
     * @return array<int|string, int>
     */
    public function overallForInstitution(Collection $users, Institution $institution): array
    {
        $package = $this->activePackage();
        if ($package === null || $users->isEmpty()) {
            return [];
        }
        $activityCount = $package->entities()
            ->where('entity_type', 'activity')
            ->where('lifecycle_status', 'published')
            ->count();
        if ($activityCount === 0) {
            return $users->mapWithKeys(static fn (User $user): array => [$user->getKey() => 0])->all();
        }

        $memberships = InstitutionMembership::query()
            ->where('institution_id', $institution->getKey())
            ->whereIn('user_id', $users->pluck('id'))
            ->where('status', InstitutionMembershipStatus::Active->value)
            ->pluck('id', 'user_id');
        $completedCounts = CurriculumActivityProgress::query()
            ->join('curriculum_entities as active_activity', function ($join) use ($package): void {
                $join->on('active_activity.code', '=', 'curriculum_activity_progress.activity_code')
                    ->where('active_activity.curriculum_package_id', '=', $package->id)
                    ->where('active_activity.entity_type', '=', 'activity')
                    ->where('active_activity.lifecycle_status', '=', 'published');
            })
            ->whereIn('curriculum_activity_progress.institution_membership_id', $memberships->values())
            ->where('curriculum_activity_progress.package_name', $package->package_name)
            ->where('curriculum_activity_progress.content_version', $package->content_version)
            ->whereNotNull('curriculum_activity_progress.completed_at')
            ->selectRaw('curriculum_activity_progress.user_id as progress_user_id, COUNT(*) as completed_count')
            ->groupBy('curriculum_activity_progress.user_id')
            ->pluck('completed_count', 'progress_user_id');

        return $users->mapWithKeys(static function (User $user) use ($completedCounts, $activityCount): array {
            return [
                $user->getKey() => (int) round(((int) ($completedCounts[$user->getKey()] ?? 0) / $activityCount) * 100),
            ];
        })->all();
    }

    private function scopeKey(User $user): string
    {
        return $this->learningContext->current(request(), $user)['scope_key'];
    }

    private function sanitizeSectionTitle(string $title): string
    {
        return (string) preg_replace('/^\s*Step\s+\d+[\.\:\-\s]*/i', '', $title);
    }
}
