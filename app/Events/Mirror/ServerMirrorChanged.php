<?php

namespace App\Events\Mirror;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast on `private-server.{serverId}` whenever a mirror row tied to
 * the server changes (allocation/database/backup/subuser/server-meta).
 * Subscribers (the React `useServerLiveUpdates(serverId)` hook) react by
 * invalidating the matching TanStack Query key — `['servers', id,
 * payload.resource]` — so the page refetches silently.
 *
 * Why ShouldBroadcastNow (not ShouldBroadcast) : the producers are
 * already queued sync jobs. Re-queuing the broadcast itself adds a
 * second worker hop (~100-500 ms) on a database queue, busting the
 * sub-second budget. `Now` runs the broadcast inline at the end of
 * the worker iteration — Reverb's HTTP API is fire-and-forget so the
 * latency cost is bounded.
 */
final class ServerMirrorChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public const RESOURCE_SERVER = 'server';
    public const RESOURCE_ALLOCATION = 'allocation';
    public const RESOURCE_BACKUP = 'backup';
    public const RESOURCE_DATABASE = 'database';
    public const RESOURCE_SUBUSER = 'subuser';

    public const ACTION_UPSERT = 'upsert';
    public const ACTION_DELETE = 'delete';

    public function __construct(
        public readonly int $serverId,
        public readonly string $resource,
        public readonly string $action,
        public readonly ?int $resourceId = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('server.'.$this->serverId);
    }

    /**
     * Short broadcast alias — Echo listens with `.mirror.changed` (the
     * leading dot tells Echo to skip the framework's `App\\Events\\…`
     * namespace prefix).
     */
    public function broadcastAs(): string
    {
        return 'mirror.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'resource' => $this->resource,
            'action' => $this->action,
            'resource_id' => $this->resourceId,
        ];
    }
}
