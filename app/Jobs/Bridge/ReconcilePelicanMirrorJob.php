<?php

namespace App\Jobs\Bridge;

use App\Services\Bridge\PelicanMirrorReconciler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * On-demand reconciliation of the local Server mirror against Pelican.
 *
 * Triggered by PelicanWebhookController when an incoming Pelican webhook
 * arrives with a malformed payload (model_id=0) for a Server CRUD event —
 * a known Pelican bug where `(array) $model` is shipped instead of
 * `$model->toArray()`. Without an id we can't act on the event directly,
 * so we run a full diff against the canonical Pelican server list.
 *
 * Debounced at dispatch time : a burst of broken webhooks (Pelican fires
 * 3-4 events per server lifecycle action — created/updated:Server,
 * updated:Allocation, updated:Server) only schedules one reconciliation
 * job. The 5-minute cron in SyncServerStatusJob remains the long-tail
 * safety net.
 */
class ReconcilePelicanMirrorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    /**
     * Cache key used to debounce dispatches. Held for `ttlSeconds` after
     * the first dispatch in a window, suppressing further dispatches until
     * it expires. The job itself is allowed to run multiple times back-to-
     * back (idempotent), so this only prevents queue bloat — the worker
     * will still process whatever lands in the queue.
     */
    private const DEBOUNCE_KEY = 'reconcile-pelican-mirror-pending';

    public function handle(PelicanMirrorReconciler $reconciler): void
    {
        $reconciler->reconcile();
    }

    /**
     * Atomic-add a debounce lock and dispatch the job only when the lock
     * was actually acquired. Returns true when a job was dispatched,
     * false when the dispatch was suppressed by an active lock.
     */
    public static function dispatchDebounced(int $ttlSeconds = 10): bool
    {
        if (! Cache::add(self::DEBOUNCE_KEY, true, $ttlSeconds)) {
            return false;
        }

        self::dispatch();

        return true;
    }
}
