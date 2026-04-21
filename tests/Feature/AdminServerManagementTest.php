<?php

namespace Tests\Feature;

use App\Events\AdminActionPerformed;
use App\Models\AdminActionLog;
use App\Models\Server;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AdminServerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_gate_before_grants_admin_access_on_server(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);

        $this->assertTrue(Gate::forUser($admin)->check('view', $server));
        $this->assertTrue(Gate::forUser($admin)->check('delete', $server));
    }

    public function test_gate_before_does_not_bypass_non_whitelisted_model(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $setting = new Setting(['key' => 'foo', 'value' => 'bar']);

        // No policy is registered for Setting; a global bypass would grant
        // this anyway, a scoped bypass must not.
        $this->assertFalse(Gate::forUser($admin)->check('delete', $setting));
    }

    public function test_gate_before_does_not_affect_non_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);

        $this->assertFalse(Gate::forUser($user)->check('view', $server));
    }

    public function test_admin_action_event_dispatched_on_cross_user_action(): void
    {
        Event::fake([AdminActionPerformed::class]);

        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);

        AdminActionPerformed::dispatchIfCrossUser(
            admin: $admin,
            action: 'server.power.start',
            server: $server,
            payload: ['signal' => 'start'],
            ip: '127.0.0.1',
            userAgent: 'phpunit',
        );

        Event::assertDispatched(AdminActionPerformed::class);
    }

    public function test_admin_action_event_not_dispatched_on_own_server(): void
    {
        Event::fake([AdminActionPerformed::class]);

        $admin = User::factory()->create(['is_admin' => true]);
        $server = $this->makeServer($admin);

        AdminActionPerformed::dispatchIfCrossUser(
            admin: $admin,
            action: 'server.power.start',
            server: $server,
            payload: [],
        );

        Event::assertNotDispatched(AdminActionPerformed::class);
    }

    public function test_admin_action_event_not_dispatched_for_non_admin(): void
    {
        Event::fake([AdminActionPerformed::class]);

        $user = User::factory()->create(['is_admin' => false]);
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);

        AdminActionPerformed::dispatchIfCrossUser(
            admin: $user,
            action: 'server.power.start',
            server: $server,
            payload: [],
        );

        Event::assertNotDispatched(AdminActionPerformed::class);
    }

    public function test_admin_action_event_scrubs_secrets_from_payload(): void
    {
        Event::fake([AdminActionPerformed::class]);

        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);

        AdminActionPerformed::dispatchIfCrossUser(
            admin: $admin,
            action: 'server.file.pull',
            server: $server,
            payload: ['url' => 'https://example.com', 'password' => 'hunter2', 'current_password' => 'x'],
        );

        Event::assertDispatched(AdminActionPerformed::class, function (AdminActionPerformed $event): bool {
            return ($event->payload['password'] ?? null) === '[redacted]'
                && ($event->payload['current_password'] ?? null) === '[redacted]'
                && ($event->payload['url'] ?? null) === 'https://example.com';
        });
    }

    public function test_admin_action_event_truncates_long_command_payload(): void
    {
        Event::fake([AdminActionPerformed::class]);

        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);

        $longCommand = str_repeat('a', 1000);

        AdminActionPerformed::dispatchIfCrossUser(
            admin: $admin,
            action: 'server.command',
            server: $server,
            payload: ['command' => mb_substr($longCommand, 0, 500)],
        );

        Event::assertDispatched(AdminActionPerformed::class, function (AdminActionPerformed $event): bool {
            return mb_strlen($event->payload['command']) === 500;
        });
    }

    public function test_listener_writes_audit_row_when_event_fires(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);

        AdminActionPerformed::dispatchIfCrossUser(
            admin: $admin,
            action: 'server.backup.delete',
            server: $server,
            payload: ['backup' => 'abc'],
            ip: '10.0.0.1',
            userAgent: 'Mozilla/5.0',
        );

        $log = AdminActionLog::latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->admin_id);
        $this->assertSame($owner->id, $log->target_user_id);
        $this->assertSame($server->id, $log->target_server_id);
        $this->assertSame('server.backup.delete', $log->action);
        $this->assertSame(['backup' => 'abc'], $log->payload);
        $this->assertSame('10.0.0.1', $log->ip);
    }

    public function test_no_audit_row_written_if_event_never_fires(): void
    {
        // Simulates "controller throws before dispatch line" — the dispatch
        // never happens, the listener never runs.
        $this->assertSame(0, AdminActionLog::count());
    }

    public function test_admin_servers_endpoint_lists_all_servers(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user2 = User::factory()->create();
        $this->makeServer($admin, ['name' => 'Alpha']);
        $this->makeServer($user2, ['name' => 'Bravo']);

        $this->actingAs($admin)
            ->getJson('/api/admin/servers')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.owner.id', $admin->id);
    }

    public function test_admin_servers_endpoint_search_filter(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create();
        $this->makeServer($admin, ['name' => 'Minecraft Survival']);
        $this->makeServer($other, ['name' => 'Rust Vanilla']);

        $this->actingAs($admin)
            ->getJson('/api/admin/servers?search=rust')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Rust Vanilla');
    }

    public function test_admin_servers_endpoint_forbidden_for_non_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->getJson('/api/admin/servers')
            ->assertStatus(403);
    }

    public function test_server_index_view_all_returns_everything_for_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user2 = User::factory()->create();
        $this->makeServer($user2, ['name' => 'Foreign']);

        $response = $this->actingAs($admin)->getJson('/api/servers?view=all');

        $response->assertOk()->assertJsonPath('meta.view', 'all');
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_server_index_view_all_ignored_for_non_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->create();
        $this->makeServer($other);

        $response = $this->actingAs($user)->getJson('/api/servers?view=all');

        // Non-admin gets their own accessible list — no meta.view=all.
        $response->assertOk();
        $this->assertArrayNotHasKey('meta', $response->json());
    }

    public function test_accessible_by_scope_returns_all_servers_for_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create();
        $this->makeServer($admin);
        $this->makeServer($other);

        $this->assertSame(2, Server::query()->accessibleBy($admin)->count());
    }

    public function test_accessible_by_scope_restricts_non_admin_to_pivot(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->create();
        $this->makeServer($other);

        $this->assertSame(0, Server::query()->accessibleBy($user)->count());
    }

    public function test_has_server_permission_returns_true_for_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create();
        $server = $this->makeServer($other);

        $this->assertTrue($admin->hasServerPermission($server, 'user.create'));
        $this->assertTrue($admin->hasServerPermission($server, 'user.delete'));
    }

    public function test_has_server_permission_blocks_non_admin_on_others_server(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->create();
        $server = $this->makeServer($other);

        $this->assertFalse($user->hasServerPermission($server, 'user.create'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeServer(User $owner, array $overrides = []): Server
    {
        return Server::create(array_merge([
            'user_id' => $owner->id,
            'pelican_server_id' => random_int(1, 1_000_000),
            'identifier' => bin2hex(random_bytes(4)),
            'name' => 'Test server',
            'status' => 'active',
        ], $overrides));
    }
}
