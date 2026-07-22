<?php

namespace App\Services\Engagement;

use App\Models\CurriculumActivityProgress;
use Carbon\CarbonImmutable;

final class ReviewScheduleService
{
    /** @param list<bool> $objectiveResults */
    public function schedule(
        CurriculumActivityProgress $progress,
        CarbonImmutable $completedAt,
        array $objectiveResults,
    ): CurriculumActivityProgress {
        $successful = $objectiveResults === [] ? null : ! in_array(false, $objectiveResults, true);
        $next = ReviewPolicy::configured()->next(
            $completedAt,
            (int) $progress->getAttribute('review_step'),
            $successful,
            $progress->getAttribute('last_reviewed_at') !== null,
        );

        $progress->forceFill([
            'review_policy_version' => $next['version'],
            'review_step' => $next['step'],
            'review_due_at' => $next['due_at'],
            'last_reviewed_at' => $completedAt,
        ])->save();

        return $progress->refresh();
    }
}
