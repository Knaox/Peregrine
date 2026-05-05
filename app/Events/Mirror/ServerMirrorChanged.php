<?php

namespace App\Events\Mirror;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast on three channel families whenever a mirror row tied to the
 * server changes (allocation/database/backup/subuser/server-meta) :
 *
 *  - `private-server.{serverId}` — detail-page subscribers (the React
 *    `useServerLiveUpdates(serverId)` hook on /server/{x}/network etc.)
 *  - `private-user.{userId}` — list-page subscribers, one per access
 *    user. /servers (DashboardPage) listens here to refresh the card
 *    grid in real-time when any of the user's servers transitions
 *    (suspended / provisioning / active / deleted).
 *  - `private-admin-mirror` — admin list-page subscribers. /admin/servers
 *    needs every change regardless of ownership, so admins join one
 *    global channel.
 *
 * Why ShouldBroadcastNow (not ShouldBroadcast) : the producers are
 * already queued sync jobs. Re-queuing the broadcast itself adds a
 * second worker hop (~100-500 ms) on a database queue, busting the
 * sub-second budget. `Now` runs the broadcast inline at the end of
 * the worker iteration — Reverb's HTTP API is fire-and-forget so the
 * latency cost is bounded.
 *
 * Access-user IDs are captured at dispatch time (NOT in broadcastOn())
 * so the deletion path still has them when the Server row is gone.
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

    /**
     * @param  array<int, int>  $accessUserIds Local user ids with pivot access to the server
     */
    public function __construct(
        public readonly int $serverId,
        public readonly string $resource,
        public readonly string $action,
        public readonly ?int $resourceId = null,
        public readonly array $accessUserIds = [],
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('server.'.$this->serverId)];

        foreach (array_unique($this->accessUserIds) as $userId) {
            $channels[] = new PrivateChannel('user.'.$userId);
        }

        $channels[] = new PrivateChannel('admin-mirror');

        return $channels;
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
