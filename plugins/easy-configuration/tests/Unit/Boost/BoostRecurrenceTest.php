<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Tests\Unit\Boost;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;
use Plugins\EasyConfiguration\Services\Boost\BoostRecurrence;

final class BoostRecurrenceTest extends TestCase
{
    private BoostRecurrence $recurrence;

    protected function setUp(): void
    {
        $this->recurrence = new BoostRecurrence;
        Carbon::setTestNow(Carbon::parse('2026-03-10 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public function test_daily_shifts_the_window_by_one_day(): void
    {
        [$start, $end] = $this->recurrence->nextWindow(
            Carbon::parse('2026-03-10 08:00:00'),
            Carbon::parse('2026-03-10 09:00:00'),
            'daily',
        );

        self::assertSame('2026-03-11 08:00:00', $start->format('Y-m-d H:i:s'));
        self::assertSame('2026-03-11 09:00:00', $end->format('Y-m-d H:i:s'));
    }

    public function test_weekly_shifts_by_seven_days(): void
    {
        [$start] = $this->recurrence->nextWindow(
            Carbon::parse('2026-03-10 08:00:00'),
            Carbon::parse('2026-03-10 09:00:00'),
            'weekly',
        );

        self::assertSame('2026-03-17 08:00:00', $start->format('Y-m-d H:i:s'));
    }

    public function test_monthly_does_not_overflow_short_months(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-31 12:00:00'));

        [$start] = $this->recurrence->nextWindow(
            Carbon::parse('2026-01-31 10:00:00'),
            Carbon::parse('2026-01-31 11:00:00'),
            'monthly',
        );

        // Jan 31 + 1 month must land on Feb 28, not spill into March.
        self::assertSame('2026-02-28 10:00:00', $start->format('Y-m-d H:i:s'));
    }

    public function test_it_skips_past_windows_to_the_next_future_one(): void
    {
        // Daily boost whose window started 9 days ago (e.g. scheduler downtime):
        // the next occurrence must be the first one in the future, not tomorrow-of-then.
        [$start] = $this->recurrence->nextWindow(
            Carbon::parse('2026-03-01 08:00:00'),
            Carbon::parse('2026-03-01 09:00:00'),
            'daily',
        );

        self::assertTrue($start->isFuture());
        self::assertSame('2026-03-11 08:00:00', $start->format('Y-m-d H:i:s'));
    }
}
