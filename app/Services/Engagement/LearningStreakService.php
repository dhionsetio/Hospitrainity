<?php

namespace App\Services\Engagement;

use App\Models\CurriculumAttempt;
use App\Models\User;
use App\Services\Time\TimeContext;
use Carbon\CarbonImmutable;

final class LearningStreakService
{
    public function __construct(private readonly TimeContext $time) {}

    public function current(User $user, string $learningScopeKey): int
    {
        if (! (bool) $user->getAttribute('learning_streak_enabled')) {
            return 0;
        }

        $zone = $this->time->displayTimeZone($user);
        $today = CarbonImmutable::instance($this->time->now())->setTimezone($zone)->startOfDay();
        $attempts = CurriculumAttempt::query()
            ->where('user_id', $user->getKey())
            ->where('learning_scope_key', $learningScopeKey)
            ->where('state', 'completed')
            ->whereNotNull('completed_at')
            ->where('completed_at', '<', $today->addDay()->utc())
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->cursor();

        $streak = 0;
        $latestDate = null;
        foreach ($attempts as $attempt) {
            $attemptDate = CarbonImmutable::parse((string) $attempt->completed_at)
                ->setTimezone($zone)
                ->startOfDay();

            if ($latestDate === null) {
                if (! $attemptDate->equalTo($today) && ! $attemptDate->equalTo($today->subDay())) {
                    return 0;
                }

                $latestDate = $attemptDate;
                $streak = 1;

                continue;
            }

            if ($attemptDate->equalTo($latestDate)) {
                continue;
            }

            if (! $attemptDate->equalTo($latestDate->subDay())) {
                break;
            }

            $latestDate = $attemptDate;
            $streak++;
        }

        return $streak;
    }
}
