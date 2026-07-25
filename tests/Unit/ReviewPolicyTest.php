<?php

namespace Tests\Unit;

use App\Services\Engagement\ReviewPolicy;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ReviewPolicyTest extends TestCase
{
    public function test_first_completion_uses_the_first_interval(): void
    {
        $policy = new ReviewPolicy('test-v1', [1, 3, 7]);
        $completed = CarbonImmutable::parse('2026-07-22 09:00:00 UTC');

        $next = $policy->next($completed, 2, true, false);

        self::assertSame('test-v1', $next['version']);
        self::assertSame(0, $next['step']);
        self::assertTrue($completed->addDay()->equalTo($next['due_at']));
    }

    public function test_success_advances_and_failure_resets_without_exceeding_policy_bounds(): void
    {
        $policy = new ReviewPolicy('test-v1', [1, 3, 7]);
        $completed = CarbonImmutable::parse('2026-07-22 09:00:00 UTC');

        $advanced = $policy->next($completed, 1, true, true);
        self::assertSame(2, $advanced['step']);
        self::assertTrue($completed->addDays(7)->equalTo($advanced['due_at']));

        $bounded = $policy->next($completed, 9, true, true);
        self::assertSame(2, $bounded['step']);

        $reset = $policy->next($completed, 2, false, true);
        self::assertSame(0, $reset['step']);
        self::assertTrue($completed->addDay()->equalTo($reset['due_at']));
    }

    public function test_unscored_review_keeps_the_current_interval(): void
    {
        $policy = new ReviewPolicy('test-v1', [1, 3, 7]);
        $completed = CarbonImmutable::parse('2026-07-22 09:00:00 UTC');

        $next = $policy->next($completed, 1, null, true);

        self::assertSame(1, $next['step']);
        self::assertTrue($completed->addDays(3)->equalTo($next['due_at']));
    }

    public function test_invalid_policy_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ReviewPolicy('test-v1', [0]);
    }
}
