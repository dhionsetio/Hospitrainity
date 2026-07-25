<?php

namespace App\Services\Engagement;

use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use App\Models\User;
use Carbon\CarbonImmutable;

final class ReviewQueueService
{
    /** @return list<array{title: string, url: string, due_at: CarbonImmutable}> */
    public function due(User $user, string $learningScopeKey, CurriculumPackage $package, int $limit = 5): array
    {
        return CurriculumEntity::query()
            ->join('curriculum_activity_progress as progress', function ($join) use ($user, $learningScopeKey, $package): void {
                $join->on('progress.activity_code', '=', 'curriculum_entities.code')
                    ->where('progress.user_id', $user->getKey())
                    ->where('progress.learning_scope_key', $learningScopeKey)
                    ->where('progress.package_name', $package->package_name)
                    ->where('progress.content_version', $package->content_version)
                    ->whereNotNull('progress.review_due_at')
                    ->where('progress.review_due_at', '<=', now());
            })
            ->where('curriculum_entities.curriculum_package_id', $package->getKey())
            ->where('curriculum_entities.entity_type', 'activity')
            ->where('curriculum_entities.lifecycle_status', 'published')
            ->orderBy('progress.review_due_at')
            ->limit(max(1, min($limit, 20)))
            ->get(['curriculum_entities.*', 'progress.review_due_at as scheduled_review_due_at'])
            ->map(static function (CurriculumEntity $activity): array {
                $title = $activity->payloadData()['title'] ?? null;

                return [
                    'title' => is_string($title) && trim($title) !== '' ? trim($title) : __('Review activity'),
                    'url' => route('curriculum.activities.show', $activity->code),
                    'due_at' => CarbonImmutable::parse((string) $activity->getAttribute('scheduled_review_due_at')),
                ];
            })
            ->all();
    }
}
