<?php

namespace App\Jobs\Bridge;

use App\Events\Bridge\ServerInstalled;
use App\Models\Server;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Polls Pelican to detect when a server's install script finishes.
 *
 * Dispatched by ProvisionServerJob right after the server is created in
 * Pelican (status starts as `installing`). Self-reschedules until Pelican
 * reports the install has finished, then fires `ServerInstalled` so the
 * "your server is playable" email goes out.
 *
 * Two modes :
 *
 *   long  — webhook is NOT configured. We're the only signal Peregrine has.
 *           20 attempts × 30s = ~10 min before we give up.
 *
 *   short — Pelican webhook is configured. The webhook usually flips the
 *           status from `provisioning` to `active` and fires `ServerInstalled`
 *           on its own. We only run as a SAFETY NET in case the webhook is
 *           misconfigured on the Pelican side or never arrives. 3 attempts
 *           at +30s, +2min, +5min. Each attempt short-circuits if the status
 *           is already `active`/`provisioning_failed` (the webhook beat us)
 *           so we never double-fire `ServerInstalled`.
 *
 * No-op (early return) cases :
 *   - Server has no pelican_server_id (provisioning was rolled back)
 *   - Status is already `active` or `provisioning_failed` (webhook beat us
 *     OR a previous attempt resolved it) → exit silently, no event fire
 *   - Pelican reports `install_failed` → log + abort, no email
 *   - Pelican unreachable transiently → reschedule
 *   - Polling exhausted → log a warning and stop (admin can retry manually)
 */
class MonitorServerInstallationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const MODE_LONG = 'long';
    public const MODE_SHORT = 'short';

    /**
     * Per-mode backoff schedule in seconds. The number of entries also caps
     * the total number of attempts.
     *
     * @var array<string, array<int, int>>
     */
    private const BACKOFFS = [
        self::MODE_LONG => [
            30, 30, 30, 30, 30, 30, 30, 30, 30, 30,
            30, 30, 30, 30, 30, 30, 30, 30, 30, 30,
        ],
        self::MODE_SHORT => [30, 90, 180],
    ];

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(
        public readonly int $serverId,
        public readonly string $mode = self::MODE_LONG,
        public readonly int $attemptNumber = 1,
    ) {}

    public function handle(PelicanApplicationService $pelican): void
    {
        $server = Server::find($this->serverId);
        if ($server === null || $server->pelican_server_id === null) {
            return;
        }

        // Short-circuit when the webhook (or a previous attempt) already
        // resolved the install state. Prevents double-firing ServerInstalled.
        if (in_array((string) $server->status, ['active', 'provisioning_failed'], true)) {
            Log::info('MonitorServerInstallationJob: short-circuit, install already resolved', [
                'server_id' => $server->id,
                'status' => $server->status,
                'mode' => $this->mode,
                'attempt' => $this->attemptNumber,
            ]);
            return;
        }

        try {
            $remote = $pelican->getServer((int) $server->pelican_server_id);
        } catch (\Throwable $e) {
            $this->rescheduleOrGiveUp(
                $server,
                "Pelican unreachable: {$e->getMessage()}"
            );
            return;
        }

        if ($remote->installFailed()) {
            Log::warning('MonitorServerInstallationJob: Pelican reports install_failed', [
                'server_id' => $server->id,
                'pelican_server_id' => $server->pelican_server_id,
            ]);
            $server->update([
                'status' => 'provisioning_failed',
                'provisioning_error' => 'Pelican install script failed (status=install_failed)',
            ]);
            return;
        }

        if ($remote->isInstalling()) {
            $this->rescheduleOrGiveUp($server, 'still installing');
            return;
        }

        // Status is null / running / offline → install finished. Flip the
        // local row to `active` and fire the event. We only fire when the
        // PREVIOUS status was `provisioning` — otherwise the webhook beat us
        // (caught above by the short-circuit), or a billing event (suspended)
        // raced in and we should not fire ServerInstalled then.
        $wasProvisioning = (string) $server->status === 'provisioning';

        if ($wasProvisioning) {
            $server->update(['status' => 'active']);
        }

        if ($wasProvisioning && $server->user !== null) {
            event(new ServerInstalled($server->fresh(), $server->user));
        }
    }

    private function rescheduleOrGiveUp(Server $server, string $reason): void
    {
        $backoffs = self::BACKOFFS[$this->mode] ?? self::BACKOFFS[self::MODE_LONG];
        $maxAttempts = count($backoffs);

        if ($this->attemptNumber >= $maxAttempts) {
            Log::warning('MonitorServerInstallationJob: gave up polling', [
                'server_id' => $server->id,
                'mode' => $this->mode,
                'attempts' => $this->attemptNumber,
                'last_reason' => $reason,
            ]);
            return;
        }

        // Backoff index = the delay BEFORE the next attempt. attemptNumber is
        // 1-based, so the next attempt's index is attemptNumber (= current + 1
        // - 1). The first dispatch (from ProvisionServerJob) carries its own
        // initial delay, so backoffs[0] applies to the gap between attempt 1
        // and attempt 2, backoffs[1] between 2 and 3, etc.
        $delay = $backoffs[$this->attemptNumber] ?? end($backoffs);

        self::dispatch($server->id, $this->mode, $this->attemptNumber + 1)
            ->delay(now()->addSeconds($delay));
    }
}
