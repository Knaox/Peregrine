<?php

declare(strict_types=1);

namespace App\Events\Mirror;

use App\Models\Server;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Drop-in trait for jobs / services that mutate `servers.status` (or
 * any other server-row column the React shell mirrors live).
 *
 * Calling `$this->broadcastServerMirrorChanged($server)` emits the
 * standard `ServerMirrorChanged` event on the three Reverb channels
 * (`server.{id}` / `user.{userId}` / `admin-mirror`) so that the
 * frontend's `useServerLiveUpdates(serverId)` and
 * `useServersListLiveUpdates()` hooks invalidate their cached
 * `['servers', id, 'server']` / `['servers']` queries within ~100 ms.
 *
 * Without this trait, the producer would have to inline the event
 * dispatch + access-user collection — observed in production to be
 * forgotten by 4 distinct status-mutating code paths
 * (`SyncServerStatusJob`, `SuspendServerJob`,
 * `SubscriptionUpdateJob` x2). The trait collapses that boilerplate
 * to one line and matches the proven pattern from the modpack-installer
 * plugin's `Concerns\SyncsServerEggId::setLocalServerStatus()`.
 *
 * Failure handling is intentional best-effort: a Reverb outage or
 * misconfiguration must NEVER bubble up and break the underlying
 * status mutation, so any throw is caught and logged at INFO level.
 * The 5 min React Query `staleTime` refetch eventually surfaces the
 * change anyway — the broadcast is purely a latency optimisation.
 */
trait BroadcastsServerMirror
{
    /**
     * Fire the `ServerMirrorChanged` event for `$server` so subscribers
     * (detail page, dashboard list, admin mirror) refresh in real time.
     *
     * Pass an explicit logger when the caller already has one in scope;
     * otherwise we fall back to Laravel's facade so we always emit
     * something on failure rather than silently swallow.
     */
    protected function broadcastServerMirrorChanged(
        Server $server,
        string $action = ServerMirrorChanged::ACTION_UPSERT,
        ?LoggerInterface $logger = null,
    ): void {
        try {
            // Pull subusers + owner from the `server_user` pivot. Modern
            // servers (post 2025_01_01_000015 migration) have the owner
            // synced into the pivot at provision time, but legacy servers
            // imported from Pelican via the bridge sometimes miss that
            // sync — `accessUsers` then returns an empty list and the
            // dashboard listener (`private-user.{id}`) gets no broadcast,
            // even though the per-server channel does. Merging the legacy
            // `user_id` column closes that gap unconditionally.
            $accessUserIds = $server->accessUsers()->pluck('users.id')->all();

            $legacyOwnerId = (int) ($server->user_id ?? 0);
            if ($legacyOwnerId > 0 && ! in_array($legacyOwnerId, $accessUserIds, true)) {
                $accessUserIds[] = $legacyOwnerId;
            }

            event(new ServerMirrorChanged(
                serverId: (int) $server->id,
                resource: ServerMirrorChanged::RESOURCE_SERVER,
                action: $action,
                resourceId: (int) $server->id,
                accessUserIds: $accessUserIds,
            ));
        } catch (Throwable $e) {
            ($logger ?? Log::channel('single'))->info(
                'BroadcastsServerMirror: ServerMirrorChanged dispatch failed (non-fatal)',
                [
                    'server_id' => $server->id,
                    'action' => $action,
                    'error' => $e->getMessage(),
                ],
            );
        }
    }
}
