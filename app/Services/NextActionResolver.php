<?php

namespace App\Services;

use App\Models\CurriculumActivityProgress;
use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class NextActionResolver
{
    public function __construct(
        private readonly OnboardingService $onboarding,
        private readonly LearningContext $learningContext,
    ) {}

    /** @param Collection<int, array<string, mixed>>|Collection<int, mixed> $chapters @return array<string, string>|null */
    public function learner(Request $request, User $user, Collection $chapters): ?array
    {
        if ($action = $this->onboardingAction($request, $user)) {
            return $action;
        }

        $package = CurriculumPackage::active();
        if ($package !== null) {
            $scope = $this->learningContext->current($request, $user)['scope_key'];
            $progress = CurriculumActivityProgress::query()
                ->where('user_id', $user->getKey())
                ->where('learning_scope_key', $scope)
                ->where('package_name', $package->package_name)
                ->where('content_version', $package->content_version)
                ->whereNull('completed_at')
                ->where(function ($query): void {
                    $query->whereNotNull('started_at')->orWhereNotNull('attempted_at')->orWhereNotNull('viewed_at');
                })
                ->latest('updated_at')
                ->first();
            if ($progress !== null) {
                $activity = CurriculumEntity::query()
                    ->where('curriculum_package_id', $package->getKey())
                    ->where('entity_type', 'activity')
                    ->published()
                    ->where('code', $progress->activity_code)
                    ->first();
                if ($activity !== null) {
                    $payload = $activity->payloadData();

                    return [
                        'eyebrow' => __('Continue'),
                        'title' => (string) ($payload['title'] ?? __('Started activity')),
                        'description' => __('Resume the most recently started activity in this learning context.'),
                        'label' => __('Resume activity'),
                        'url' => route('curriculum.activities.show', $activity->code),
                    ];
                }
            }
        }

        $chapter = $chapters->first(fn ($item): bool => (float) data_get($item, 'progress', 0) < 100);
        if ($chapter !== null) {
            $started = (int) $chapter['progress'] > 0;

            return [
                'eyebrow' => $started ? __('Continue') : __('Start learning'),
                'title' => (string) $chapter['title'],
                'description' => $started ? __('Continue the next incomplete module in this learning context.') : __('Open the first published module that is not yet complete.'),
                'label' => $started ? __('Continue module') : __('Open module'),
                'url' => route('curriculum.chapters.show', $chapter['code']),
            ];
        }

        if ($chapters->isNotEmpty()) {
            return [
                'eyebrow' => __('Review'),
                'title' => __('All published activities are complete'),
                'description' => __('Review your optional confidence history or revisit any published module.'),
                'label' => __('View confidence history'),
                'url' => route('curriculum.confidence-history'),
            ];
        }

        return null;
    }

    /** @return array<string, string> */
    public function supervisor(Request $request, User $user, LengthAwarePaginator $learners): array
    {
        if ($action = $this->onboardingAction($request, $user)) {
            return $action;
        }

        $first = $learners->getCollection()->first();
        if ($first !== null) {
            return [
                'eyebrow' => __('Institution work'),
                'title' => __('Review learner participation'),
                'description' => __('Open the first learner in the current institution scope or use the bounded list below.'),
                'label' => __('View learner progress'),
                'url' => route('supervisor.progress.learners.show', $first),
            ];
        }

        return [
            'eyebrow' => __('Institution setup'),
            'title' => __('No approved learners in this institution yet'),
            'description' => __('Invite one learner or create a time-limited classroom code. Institution approval is still required.'),
            'label' => __('Invite a learner'),
            'url' => route('supervisor.invitations.index'),
        ];
    }

    /** @param array<string, int> $draftCounts @return array<string, string> */
    public function administration(Request $request, User $user, array $draftCounts): array
    {
        if ($action = $this->onboardingAction($request, $user)) {
            return $action;
        }

        $prefix = $user->isSuperAdmin() ? 'superadmin' : 'admin';
        if (($draftCounts['in_review'] ?? 0) > 0) {
            return [
                'eyebrow' => __('Content review'),
                'title' => __('Review content awaiting a decision'),
                'description' => __('Open the versioned content queue. Exact source evidence remains available inside each authorized workspace.'),
                'label' => __('Review canonical content'),
                'url' => route($prefix.'.curriculum-drafts.index'),
            ];
        }
        if (($draftCounts['editable'] ?? 0) > 0) {
            return [
                'eyebrow' => __('Content work'),
                'title' => __('Continue an editable content workspace'),
                'description' => __('Resume current draft work and preview it before any review or publication step.'),
                'label' => __('Open canonical content'),
                'url' => route($prefix.'.curriculum-drafts.index'),
            ];
        }

        return [
            'eyebrow' => __('Content work'),
            'title' => __('No editable content workspace'),
            'description' => __('Open canonical content to review published work or begin an authorized draft workflow.'),
            'label' => __('Open canonical content'),
            'url' => route($prefix.'.curriculum-drafts.index'),
        ];
    }

    /** @return array<string, string>|null */
    private function onboardingAction(Request $request, User $user): ?array
    {
        $snapshot = $this->onboarding->snapshot($request, $user);
        if (! in_array($snapshot['status'], ['pending', 'in_progress'], true)) {
            return null;
        }
        $step = $snapshot['steps'][$snapshot['current_step']];

        return [
            'eyebrow' => __('Getting started'),
            'title' => $step['title'],
            'description' => $step['description'],
            'label' => $snapshot['status'] === 'pending' ? __('Start short guide') : __('Resume short guide'),
            'url' => route('onboarding.show'),
        ];
    }
}
