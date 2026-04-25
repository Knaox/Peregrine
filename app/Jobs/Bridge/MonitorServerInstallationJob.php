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
 * Pelican (status starts as `installing`). Self-reschedules every 30s for
 * up to MAX_ATTEMPTS tries (= ~10 minutes total). When Pelican reports the
 * status has flipped out of `installing`, fires `ServerInstalled` so the
 * listener can mail the customer.
 *
 * No-op (early return) cases :
 *   - Server has no pelican_server_id (provisioning was rolled back)
 *   - Pelican reports `install_failed` → log + abort, no email
 *   - Pelican unreachable transiently → reschedule (Pelican may come back)
 *   - Polling exhausted → log a warning and stop (admin can retry manually)
 *
 * Why polling and not the Pelican webhook event ? The webhook
 * `event: Server\Installed` requires opening the Pelican webhook receiver
 * to shop_stripe mode (currently paymenter-only). Polling is self-contained
 * and works against any Pelican install without admin-side webhook config.
 */
class MonitorServerInstallationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_ATTEMPTS = 20;
    private const POLL_DELAY_SECONDS = 30;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(
        public readonly int $serverId,
        public readonly int $attemptNumber = 1,
    ) {}

    public function handle(PelicanApplicationService $pelican): void
    {
        $server = Server::find($this->serverId);
        if ($server === null || $server->pelican_server_id === null) {
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
        // local row to `active` (defensive — the provisioning job already set
        // it but a stale `provisioning` state can survive) and fire the event.
        if ($server->status !== 'active') {
            $server->update(['status' => 'active']);
        }
        if ($server->user !== null) {
            event(new ServerInstalled($server->fresh(), $server->user));
        }
    }

    private function rescheduleOrGiveUp(Server $server, string $reason): void
    {
        if ($this->attemptNumber >= self::MAX_ATTEMPTS) {
            Log::warning('MonitorServerInstallationJob: gave up polling', [
                'server_id' => $server->id,
                'attempts' => $this->attemptNumber,
                'last_reason' => $reason,
            ]);
            return;
        }
        self::dispatch($server->id, $this->attemptNumber + 1)
            ->delay(now()->addSeconds(self::POLL_DELAY_SECONDS));
    }
}
