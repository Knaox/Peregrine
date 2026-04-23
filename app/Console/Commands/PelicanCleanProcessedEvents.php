<?php

namespace App\Console\Commands;

use App\Models\PelicanProcessedEvent;
use Illuminate\Console\Command;

/**
 * Daily cleanup of the Pelican webhook idempotency ledger.
 *
 * Pelican does NOT retry failed deliveries — once we've responded (or even
 * not), the event is gone forever. There's no replay window to honour.
 * We keep 24h of rows for forensic / debug value (admin can answer "did
 * we receive event X 4 hours ago?") and prune older rows here.
 *
 * Scheduled in `routes/console.php` daily at 03:45 (just after the Stripe
 * cleanup so logs are easy to correlate).
 */
class PelicanCleanProcessedEvents extends Command
{
    protected $signature = 'pelican:clean-processed-events {--days=2 : Retention window (default 2 days)}';

    protected $description = 'Prune pelican_processed_events rows older than the retention window';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days < 1) {
            $this->error('--days must be >= 1.');
            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $deleted = PelicanProcessedEvent::where('processed_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} rows older than {$cutoff->toIso8601String()}.");
        return self::SUCCESS;
    }
}
