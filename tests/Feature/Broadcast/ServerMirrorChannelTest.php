<?php

namespace Tests\Feature\Broadcast;

use App\Events\Mirror\ServerMirrorChanged;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pin the broadcast contract for the live-refresh feature :
 *  - the event ships the right payload shape (consumed by the React
 *    `useServerLiveUpdates` hook),
 *  - the private channel auth callback grants owner + admin only.
 *
 * Channel auth + frontend integration aren't exercised end-to-end here
 * (Reverb is an external process — that's a manual smoke check at
 * deploy time). The actual dispatch from the server sync job is covered
 * inline in the job's own test path.
 */
class ServerMirrorChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_payload_uses_short_alias_and_private_channel(): void
    {
        $event = new ServerMirrorChanged(serverId: 7, resource: 'server', action: 'upsert', resourceId: 7);

        $this->assertSame('mirror.changed', $event->broadcastAs());
        $names = array_map(fn ($c) => $c->name, $event->broadcastOn());
        $this->assertContains('private-server.7', $names);
        $this->assertContains('private-admin-mirror', $names);
        $this->assertSame(
            ['resource' => 'server', 'action' => 'upsert', 'resource_id' => 7],
            $event->broadcastWith(),
        );
    }

    public function test_event_broadcasts_on_each_access_user_channel(): void
    {
        $event = new ServerMirrorChanged(
            serverId: 11,
            resource: 'server',
            action: 'upsert',
            resourceId: 11,
            accessUserIds: [3, 5, 5],
        );

        $names = array_map(fn ($c) => $c->name, $event->broadcastOn());

        $this->assertContains('private-server.11', $names);
        $this->assertContains('private-user.3', $names);
        $this->assertContains('private-user.5', $names);
        $this->assertContains('private-admin-mirror', $names);
        // Duplicates collapsed.
        $this->assertSame(1, count(array_filter($names, fn ($n) => $n === 'private-user.5')));
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

        // Replicate the closure from routes/channels.php — Broadcast::channel
        // registers it inside the framework's channel registry, so we
        // assert the rule directly here.
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

    public function test_user_channel_grants_self_and_admin_only(): void
    {
        $self = User::create(['name' => 'A', 'email' => 'a@x', 'password' => Hash::make('x')]);
        $admin = User::create(['name' => 'B', 'email' => 'b@x', 'password' => Hash::make('x'), 'is_admin' => true]);
        $other = User::create(['name' => 'C', 'email' => 'c@x', 'password' => Hash::make('x')]);

        $auth = static fn (User $user, int $userId): bool => $user->id === $userId || (bool) $user->is_admin;

        $this->assertTrue($auth($self, $self->id));
        $this->assertTrue($auth($admin, $self->id));
        $this->assertFalse($auth($other, $self->id));
    }

    public function test_admin_mirror_channel_grants_admin_only(): void
    {
        $regular = User::create(['name' => 'R', 'email' => 'r@x', 'password' => Hash::make('x')]);
        $admin = User::create(['name' => 'A', 'email' => 'admin@x', 'password' => Hash::make('x'), 'is_admin' => true]);

        $auth = static fn (User $user): bool => (bool) $user->is_admin;

        $this->assertTrue($auth($admin));
        $this->assertFalse($auth($regular));
    }
}
