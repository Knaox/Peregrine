<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Console;

use Illuminate\Console\Command;
use Plugins\EasyConfiguration\Jobs\ApplyBoostJob;
use Plugins\EasyConfiguration\Jobs\EndBoostJob;
use Plugins\EasyConfiguration\Models\BoostSchedule;

/**
 * Scheduler tick (every minute): dispatch ApplyBoostJob for pending boosts that
 * have reached their start, and EndBoostJob for active boosts past their end.
 * The jobs do the heavy stop/write/start work off the scheduler thread.
 */
final class CheckBoostsCommand extends Command
{
    protected $signature = 'easy-config:check-boosts';

    protected $description = 'Apply due Easy Configuration boosts and end expired ones.';

    public function handle(): int
    {
        $now = now();

        BoostSchedule::query()
            ->where('status', 'pending')
            ->where('start_at', '<=', $now)
            ->pluck('id')
            ->each(static fn (int $id) => ApplyBoostJob::dispatch($id));

        BoostSchedule::query()
            ->where('status', 'active')
            ->where('end_at', '<=', $now)
            ->pluck('id')
            ->each(static fn (int $id) => EndBoostJob::dispatch($id, 'completed'));

        return self::SUCCESS;
    }
}
