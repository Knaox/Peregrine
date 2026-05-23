<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Boost;

use Illuminate\Support\Carbon;

/**
 * Pure date math for recurring boosts. Given a completed boost's window and its
 * recurrence unit, returns the next window: the same start/end shifted by one
 * interval, skipped forward past any windows already elapsed (e.g. scheduler
 * downtime) to the first future one. Bounded to avoid a runaway loop.
 */
final class BoostRecurrence
{
    private const MAX_SKIP = 4000;

    /**
     * @param  'daily'|'weekly'|'monthly'|string  $recurrence
     * @return array{0: Carbon, 1: Carbon}
     */
    public function nextWindow(Carbon $startAt, Carbon $endAt, string $recurrence): array
    {
        $advance = static fn (Carbon $date): Carbon => match ($recurrence) {
            'weekly' => $date->copy()->addWeek(),
            'monthly' => $date->copy()->addMonthNoOverflow(),
            default => $date->copy()->addDay(),
        };

        $nextStart = $advance($startAt);
        $nextEnd = $advance($endAt);

        for ($guard = 0; $nextStart->isPast() && $guard < self::MAX_SKIP; $guard++) {
            $nextStart = $advance($nextStart);
            $nextEnd = $advance($nextEnd);
        }

        return [$nextStart, $nextEnd];
    }
}
