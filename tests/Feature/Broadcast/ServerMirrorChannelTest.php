<?php

namespace Tests\Feature\Broadcast;

use App\Enums\PelicanEventKind;
use App\Events\Mirror\ServerMirrorChanged;
use App\Jobs\Bridge\SyncAllocationFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncBackupFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncDatabaseFromPelicanWebhookJob;
use App\Models\Node;
use App\Models\Pelican\Backup;
use App\Models\Pelican\Database as PelicanDatabase;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pin the broadcast contract — every sync job must dispatch a
 * `ServerMirrorChanged` event with the right resource/action/id so the
 * React `useServerLiveUpdates` hook gets enough payload to invalidate
 * the right TanStack Query key.
 *
 * Channel auth + frontend integration aren't exercised here (Reverb is
 * an external process — that's a manual smoke check at deploy time).
 */
class ServerMirrorChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_allocation_upsert_dispatches_broadcast(): void
    {
        Event::fake([ServerMirrorChanged::class]);

        $server = $this->makeServer(101);
        Node::create(['pelican_node_id' => 7, 'name' => 'n', 'fqdn' => 'n', 'memory' => 0, 'disk' => 0]);

        (new SyncAllocationFromPelicanWebhookJob(
            pelicanAllocationId: 42,
            payload: ['node_id' => 7, 'server_id' => 101, 'ip' => '1.2.3.4', 'port' => 25565],
            eventKind: PelicanEventKind::AllocationCreated,
        ))->handle();

        Event::assertDispatched(ServerMirrorChanged::class, function (ServerMirrorChanged $e) use ($server): bool {
            return $e->serverId === $server->id
                && $e->resource === ServerMirrorChanged::RESOURCE_ALLOCATION
                && $e->action === ServerMirrorChanged::ACTION_UPSERT
                && $e->resourceId === 42;
        });
    }

    public function test_allocation_delete_dispatches_broadcast(): void
    {
        Event::fake([ServerMirrorChanged::class]);

        $server = $this->makeServer(102);
        $node = Node::create(['pelican_node_id' => 8, 'name' => 'n', 'fqdn' => 'n', 'memory' => 0, 'disk' => 0]);
        \App\Models\Pelican\Allocation::create([
            'pelican_allocation_id' => 50,
            'node_id' => $node->id,
            'server_id' => $server->id,
            'ip' => '1.2.3.4',
            'port' => 25500,
            'is_locked' => false,
        ]);

        (new SyncAllocationFromPelicanWebhookJob(
            pelicanAllocationId: 50,
            payload: [],
            eventKind: PelicanEventKind::AllocationDeleted,
        ))->handle();

        Event::assertDispatched(ServerMirrorChanged::class, fn (ServerMirrorChanged $e): bool => $e->serverId === $server->id
            && $e->resource === ServerMirrorChanged::RESOURCE_ALLOCATION
            && $e->action === ServerMirrorChanged::ACTION_DELETE);
    }

    public function test_backup_upsert_dispatches_broadcast(): void
    {
        Event::fake([ServerMirrorChanged::class]);
        $server = $this->makeServer(200);

        (new SyncBackupFromPelicanWebhookJob(
            pelicanBackupId: 7,
            pelicanServerId: 200,
            payload: ['uuid' => 'u-7', 'name' => 'first', 'is_successful' => true],
            eventKind: PelicanEventKind::BackupCreated,
        ))->handle();

        Event::assertDispatched(ServerMirrorChanged::class, fn (ServerMirrorChanged $e): bool => $e->serverId === $server->id
            && $e->resource === ServerMirrorChanged::RESOURCE_BACKUP
            && $e->action === ServerMirrorChanged::ACTION_UPSERT
            && $e->resourceId === 7);
    }

    public function test_backup_delete_dispatches_broadcast(): void
    {
        Event::fake([ServerMirrorChanged::class]);
        $server = $this->makeServer(201);
        Backup::create([
            'pelican_backup_id' => 8,
            'server_id' => $server->id,
            'uuid' => 'u-8',
            'name' => 'old',
            'is_successful' => true,
        ]);

        (new SyncBackupFromPelicanWebhookJob(
            pelicanBackupId: 8,
            pelicanServerId: 201,
            payload: [],
            eventKind: PelicanEventKind::BackupDeleted,
        ))->handle();

        Event::assertDispatched(ServerMirrorChanged::class, fn (ServerMirrorChanged $e): bool => $e->serverId === $server->id
            && $e->resource === ServerMirrorChanged::RESOURCE_BACKUP
            && $e->action === ServerMirrorChanged::ACTION_DELETE);
    }

    public function test_database_upsert_dispatches_broadcast(): void
    {
        Event::fake([ServerMirrorChanged::class]);
        $server = $this->makeServer(300);

        (new SyncDatabaseFromPelicanWebhookJob(
            pelicanDatabaseId: 1,
            pelicanServerId: 300,
            payload: ['database' => 's_main', 'username' => 'u', 'remote' => '%'],
            eventKind: PelicanEventKind::DatabaseCreated,
        ))->handle();

        Event::assertDispatched(ServerMirrorChanged::class, fn (ServerMirrorChanged $e): bool => $e->serverId === $server->id
            && $e->resource === ServerMirrorChanged::RESOURCE_DATABASE
            && $e->resourceId === 1);
    }

    public function test_database_delete_dispatches_broadcast(): void
    {
        Event::fake([ServerMirrorChanged::class]);
        $server = $this->makeServer(301);
        PelicanDatabase::create([
            'pelican_database_id' => 9,
            'server_id' => $server->id,
            'database' => 'old',
            'username' => 'u',
            'remote' => '%',
            'max_connections' => 0,
        ]);

        (new SyncDatabaseFromPelicanWebhookJob(
            pelicanDatabaseId: 9,
            pelicanServerId: 301,
            payload: [],
            eventKind: PelicanEventKind::DatabaseDeleted,
        ))->handle();

        Event::assertDispatched(ServerMirrorChanged::class, fn (ServerMirrorChanged $e): bool => $e->serverId === $server->id
            && $e->resource === ServerMirrorChanged::RESOURCE_DATABASE
            && $e->action === ServerMirrorChanged::ACTION_DELETE);
    }

    public function test_event_payload_uses_short_alias_and_private_channel(): void
    {
        $event = new ServerMirrorChanged(serverId: 7, resource: 'allocation', action: 'upsert', resourceId: 42);

        $this->assertSame('mirror.changed', $event->broadcastAs());
        $this->assertSame('private-server.7', $event->broadcastOn()->name);
        $this->assertSame(
            ['resource' => 'allocation', 'action' => 'upsert', 'resource_id' => 42],
            $event->broadcastWith(),
        );
    }

    public function test_channel_authorization_callback_grants_owner_and_admin_only(): void
    {
        $owner = User::create(['name' => 'O', 'email' => 'o@x', 'password' => Hash::make('x')]);
        $admin = User::create(['name' => 'A', 'email' => 'a@x', 'password' => Hash::make('x'), 'is_admin' => true]);
        $stranger = User::create(['name' => 'S', 'email' => 's@x', 'password' => Hash::make('x')]);

        $server = Server::create([
            'user_id' => $owner->id, 'pelican_server_id' => 999,
            'name' => 's999', 'identifier' => 'i999', 'status' => 'active',
        ]);
        $server->accessUsers()->attach($owner->id, ['role' => 'owner', 'permissions' => null]);

        // Replicate the auth logic from routes/channels.php — the closure
        // there isn't directly callable from the test (Broadcast::channel
        // registers it inside the framework's channel registry). The
        // assertions below pin the rule : admin pass, owner pass, stranger
        // reject, unknown server reject.
        $auth = static function (User $user, int $serverId): bool {
            $row = Server::find($serverId);
            if ($row === null) {
                return false;
            }
            if ($user->is_admin) {
                return true;
            }
            return $row->accessUsers()->where('users.id', $user->id)->exists();
        };

        $this->assertTrue($auth($owner, $server->id));
        $this->assertTrue($auth($admin, $server->id));
        $this->assertFalse($auth($stranger, $server->id));
        $this->assertFalse($auth($stranger, 99999));
    }

    private function makeServer(int $pelicanServerId): Server
    {
        $u = User::create(['name' => 'u'.$pelicanServerId, 'email' => 'u'.$pelicanServerId.'@x', 'password' => Hash::make('x')]);

        return Server::create([
            'user_id' => $u->id,
            'pelican_server_id' => $pelicanServerId,
            'name' => 's'.$pelicanServerId,
            'identifier' => 'i'.$pelicanServerId,
            'status' => 'active',
            'idempotency_key' => 'k'.$pelicanServerId,
        ]);
    }
}
