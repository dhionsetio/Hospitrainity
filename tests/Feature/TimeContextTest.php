<?php

namespace Tests\Feature;

use App\Enums\LocalTimeDisambiguation;
use App\Models\CourseOffering;
use App\Models\Institution;
use App\Models\User;
use App\Services\Time\TimeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Tests\TestCase;

class TimeContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_clock_is_utc_and_time_context_is_deterministic(): void
    {
        $clock = app(ClockInterface::class);
        $this->assertSame('UTC', $clock->now()->getTimezone()->getName());

        $time = new TimeContext(new MockClock('2026-07-21 08:15:00 UTC'));
        $this->assertSame('2026-07-21T08:15:00+00:00', $time->now()->format(DATE_ATOM));
    }

    public function test_schedule_and_display_timezone_precedence_are_explicit(): void
    {
        $time = new TimeContext(new MockClock('2026-07-21 08:15:00 UTC'));
        $institution = new Institution(['timezone' => 'Asia/Jakarta']);
        $institution->id = '10000000-0000-4000-8000-000000000001';
        $offering = new CourseOffering(['timezone' => 'Asia/Singapore']);
        $offering->institution_id = $institution->id;
        $user = new User(['timezone' => 'Europe/Amsterdam']);

        $this->assertSame('Asia/Singapore', $time->scheduleTimeZone($institution, $offering)->getName());
        $this->assertSame('Europe/Amsterdam', $time->displayTimeZone($user, $institution, $offering)->getName());
        $this->assertSame('UTC', $time->scheduleTimeZone()->getName());
        $this->assertSame('2026-07-21 10:15:00 CEST', $time->nowForDisplay($user)->format('Y-m-d H:i:s T'));
    }

    public function test_class_context_resolves_its_institution_fallback_when_caller_omits_the_model(): void
    {
        $time = new TimeContext(new MockClock);
        $institution = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $offering = new CourseOffering;
        $offering->institution_id = $institution->id;

        $this->assertSame('Asia/Jakarta', $time->scheduleTimeZone(null, $offering)->getName());
        $this->assertSame('Asia/Jakarta', $time->displayTimeZone(null, null, $offering)->getName());
    }

    public function test_dst_gap_is_rejected_instead_of_silently_normalized(): void
    {
        $time = new TimeContext(new MockClock);

        $this->expectException(InvalidArgumentException::class);
        $time->localWallTimeToUtc('2026-03-29 02:30:00', 'Europe/Amsterdam');
    }

    public function test_dst_fold_requires_explicit_disambiguation_and_returns_both_valid_instants(): void
    {
        $time = new TimeContext(new MockClock);

        try {
            $time->localWallTimeToUtc('2026-10-25 02:30:00', 'Europe/Amsterdam');
            $this->fail('An ambiguous local wall time unexpectedly defaulted to an instant.');
        } catch (InvalidArgumentException) {
            $earlier = $time->localWallTimeToUtc(
                '2026-10-25 02:30:00',
                'Europe/Amsterdam',
                LocalTimeDisambiguation::Earlier,
            );
            $later = $time->localWallTimeToUtc(
                '2026-10-25 02:30:00',
                'Europe/Amsterdam',
                LocalTimeDisambiguation::Later,
            );

            $this->assertSame('2026-10-25 00:30:00 UTC', $earlier->format('Y-m-d H:i:s T'));
            $this->assertSame('2026-10-25 01:30:00 UTC', $later->format('Y-m-d H:i:s T'));
            $this->assertSame(3600, $later->getTimestamp() - $earlier->getTimestamp());
        }
    }

    public function test_mismatched_class_and_institution_context_fails_closed(): void
    {
        $time = new TimeContext(new MockClock);
        $institution = new Institution(['timezone' => 'Asia/Jakarta']);
        $institution->id = '10000000-0000-4000-8000-000000000001';
        $offering = new CourseOffering(['timezone' => 'Asia/Singapore']);
        $offering->institution_id = '20000000-0000-4000-8000-000000000002';

        $this->expectException(InvalidArgumentException::class);
        $time->displayTimeZone(null, $institution, $offering);
    }
}
