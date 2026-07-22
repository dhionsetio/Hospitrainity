<?php

namespace Tests\Feature;

use App\Models\CurriculumAttempt;
use App\Models\User;
use App\Services\Engagement\LearningStreakService;
use App\Services\Time\TimeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\Clock\MockClock;
use Tests\TestCase;

class LearningStreakServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_streak_counts_distinct_consecutive_days_in_the_learners_timezone(): void
    {
        $user = User::factory()->create([
            'timezone' => 'Asia/Jakarta',
            'learning_streak_enabled' => true,
        ]);
        $this->attempt($user, 'personal', '2026-07-20 01:00:00');
        $this->attempt($user, 'personal', '2026-07-21 01:00:00');
        $this->attempt($user, 'personal', '2026-07-22 01:00:00');
        $this->attempt($user, 'personal', '2026-07-22 02:00:00');
        $this->attempt($user, 'another-scope', '2026-07-19 01:00:00');

        $service = new LearningStreakService(new TimeContext(new MockClock('2026-07-22 08:00:00 UTC')));

        self::assertSame(3, $service->current($user, 'personal'));
    }

    public function test_yesterdays_streak_remains_current_but_an_older_gap_returns_zero(): void
    {
        $user = User::factory()->create([
            'timezone' => 'Asia/Jakarta',
            'learning_streak_enabled' => true,
        ]);
        $this->attempt($user, 'personal', '2026-07-20 01:00:00');
        $this->attempt($user, 'personal', '2026-07-21 01:00:00');
        $service = new LearningStreakService(new TimeContext(new MockClock('2026-07-22 08:00:00 UTC')));

        self::assertSame(2, $service->current($user, 'personal'));

        $later = new LearningStreakService(new TimeContext(new MockClock('2026-07-24 08:00:00 UTC')));
        self::assertSame(0, $later->current($user, 'personal'));
    }

    public function test_disabled_streak_returns_zero(): void
    {
        $user = User::factory()->create([
            'learning_streak_enabled' => false,
        ]);
        $this->attempt($user, 'personal', '2026-07-22 01:00:00');
        $service = new LearningStreakService(new TimeContext(new MockClock('2026-07-22 08:00:00 UTC')));

        self::assertSame(0, $service->current($user, 'personal'));
    }

    public function test_streak_is_not_silently_capped_after_one_year(): void
    {
        $user = User::factory()->create([
            'timezone' => 'UTC',
            'learning_streak_enabled' => true,
        ]);
        $today = now()->setDate(2026, 7, 22)->setTime(1, 0);
        for ($day = 0; $day < 400; $day++) {
            $this->attempt($user, 'personal', $today->copy()->subDays($day)->toDateTimeString());
        }

        $service = new LearningStreakService(new TimeContext(new MockClock('2026-07-22 08:00:00 UTC')));

        self::assertSame(400, $service->current($user, 'personal'));
    }

    private function attempt(User $user, string $scope, string $completedAt): void
    {
        CurriculumAttempt::query()->create([
            'user_id' => $user->getKey(),
            'learning_scope_key' => $scope,
            'package_name' => 'hospitrainity',
            'content_version' => 'test',
            'activity_code' => 'ACT-'.Str::lower(Str::random(8)),
            'activity_source_sha256' => str_repeat('a', 64),
            'idempotency_key' => (string) Str::uuid(),
            'submission_hmac_sha256' => str_repeat('b', 64),
            'intent' => 'check',
            'state' => 'completed',
            'completion_reason' => 'participation_rule_satisfied',
            'started_at' => $completedAt,
            'attempted_at' => $completedAt,
            'completed_at' => $completedAt,
        ]);
    }
}
