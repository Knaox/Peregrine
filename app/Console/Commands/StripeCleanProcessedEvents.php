<?php

namespace App\Console\Commands;

use App\Models\StripeProcessedEvent;
use Illuminate\Console\Command;

/**
 * Daily cleanup of the Stripe webhook idempotency ledger.
 *
 * Stripe re-delivers events for at most 3 days when our endpoint fails.
 * After that window, an event_id can never be replayed safely, so keeping
 * it in the table is just storage waste. We retain 30 days for forensic
 * value (debugging "did we receive event X ?" 2 weeks later) and prune
 * older rows here.
 *
 * Scheduled in `routes/console.php`.
 */
class StripeCleanProcessedEvents extends Command
{
    protected $signature = 'stripe:clean-processed-events {--days=30 : Retention window (default 30 days)}';

    protected $description = 'Prune stripe_processed_events rows older than the retention window';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days < 7) {
            $this->error("--days must be >= 7 (Stripe retry window is 3 days; keeping a buffer is safer).");
            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $deleted = StripeProcessedEvent::where('processed_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} rows older than {$cutoff->toIso8601String()}.");
        return self::SUCCESS;
    }
}
