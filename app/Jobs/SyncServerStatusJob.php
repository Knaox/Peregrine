<?php

namespace App\Jobs;

use App\Events\Mirror\BroadcastsServerMirror;
use App\Models\Server;
use App\Services\Bridge\PelicanMirrorReconciler;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\Pelican\PelicanClientService;
use App\Services\Sync\ServerStatusResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncServerStatusJob implements ShouldQueue
{
    use BroadcastsServerMirror;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(
        PelicanClientService $clientService,
        PelicanMirrorReconciler $reconciler,
        PelicanApplicationService $applicationService,
    ): void {
        // Provisioning fallback runs FIRST so install completion lands in
        // `active` before the runtime sweep below can mistake an empty
        // /resources response (Pelican often answers 409 / 500 on a
        // server mid-install) for an `offline` runtime and clobber the
        // status. Catching the install-end first keeps the sweep
        // semantically clean : "provisioning" is gone before "is the
        // process running?" is even asked.
        $this->reconcileProvisioningStatuses($applicationService);

        $this->syncRuntimeStatuses($clientService);

        // Paymenter mode safety net : Pelican does not retry failed webhooks,
        // so the reconciler diffs the local mirror against Pelican's full
        // server list on every cron tick. No-ops outside Paymenter mode.
        $reconciler->reconcile();
    }

    private function syncRuntimeStatuses(PelicanClientService $clientService): void
    {
        $servers = Server::whereNotNull('identifier')->get();

        foreach ($servers as $server) {
            // Skip servers we already moved out of `provisioning` in this
            // tick — and skip ones we left there on purpose (the
            // Application API still reports `installing`). The client API
            // would return either an error or a misleading `offline`
            // state during install, both of which would clobber the
            // intentional `provisioning` flag.
            if ($server->status === 'provisioning') {
                continue;
            }

            try {
                $resources = $clientService->getServerResources($server->identifier);

                $newStatus = match ($resources->state) {
                    'running', 'starting' => 'running',
                    'stopping', 'stopped' => 'stopped',
                    'offline' => 'offline',
                    default => $server->status,
                };

                if ($server->status !== $newStatus && ! in_array($server->status, ['suspended', 'terminated'], true)) {
                    $server->update(['status' => $newStatus]);
                    // Broadcast so the dashboard list and the detail
                    // page reflect runtime crashes / restarts within
                    // ~100 ms instead of waiting on the 5-min React
                    // Query staleTime refetch.
                    $this->broadcastServerMirrorChanged($server);
                }
            } catch (\Throwable) {
                // If API call fails, mark as offline (unless suspended/terminated)
                if (! in_array($server->status, ['suspended', 'terminated'], true)) {
                    $previousStatus = $server->status;
                    $server->update(['status' => 'offline']);
                    if ($previousStatus !== 'offline') {
                        $this->broadcastServerMirrorChanged($server);
                    }
                }
            }
        }
    }

    /**
     * Webhook-loss safety net for servers stuck in `provisioning`.
     *
     * Reinstalls (server-level + plugin uninstall phase 2) flip the local
     * status to `provisioning` and rely SOLELY on Pelican's
     * `Server\Installed` webhook to flip it back to `active`. If that
     * webhook never arrives — Pelican misconfigured (no webhook URL,
     * wrong secret), local network blip, container restart between
     * dispatch and delivery, … — Peregrine would otherwise stay
     * convinced the install is still running forever, locking the
     * sidebar and the conflict gate.
     *
     * Strategy : on every cron tick, ask Pelican's Application API
     * (source of truth, NOT the client API which reports runtime state)
     * whether each provisioning server is still installing. If Pelican
     * says `null` / `running` / `install_failed`, we apply the same
     * mapping the webhook handler would and broadcast the resulting
     * `mirror.changed` so subscribers refresh in real time.
     *
     * Idempotent with the webhook path : `resolveInstallStatus()`
     * returns null when the server isn't actually in `provisioning`
     * locally any more (the webhook beat us to it), so a double-fire
     * is a no-op.
     */
    private function reconcileProvisioningStatuses(PelicanApplicationService $applicationService): void
    {
        $servers = Server::query()
            ->where('status', 'provisioning')
            ->whereNotNull('pelican_server_id')
            ->get();

        foreach ($servers as $server) {
            try {
                $apiSnapshot = $applicationService->getServer((int) $server->pelican_server_id);
            } catch (\Throwable $e) {
                // Application API blip — we'll retry next tick. Logging
                // at info because a transient miss is normal during a
                // Pelican restart and we don't want to wake the operator.
                Log::info('SyncServerStatusJob: provisioning fallback API miss', [
                    'server_id' => $server->id,
                    'pelican_server_id' => $server->pelican_server_id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $resolved = ServerStatusResolver::resolveInstallStatus(
                $apiSnapshot,
                'provisioning',
                [],
            );

            if ($resolved === null || $resolved === 'provisioning') {
                continue;
            }

            $server->update(['status' => $resolved]);
            $this->broadcastServerMirrorChanged($server);

            Log::info('SyncServerStatusJob: provisioning fallback unblocked server', [
                'server_id' => $server->id,
                'pelican_server_id' => $server->pelican_server_id,
                'new_status' => $resolved,
                'note' => 'webhook-loss safety net — check Pelican webhook config if this fires repeatedly',
            ]);
        }
    }
}
