<?php

namespace Tests\Feature\Bridge;

use App\Enums\BridgeMode;
use App\Events\Bridge\ServerProvisioned;
use App\Jobs\Bridge\SyncServerFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncUserFromPelicanWebhookJob;
use App\Jobs\SyncServerStatusJob;
use App\Models\Server;
use App\Models\Setting;
use App\Models\User;
use App\Services\Pelican\DTOs\PelicanServer;
use App\Services\Pelican\DTOs\PelicanUser;
use App\Services\Pelican\DTOs\ServerLimits;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\Pelican\PelicanClientService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

/**
 * Sprint 3 — Bridge Paymenter mirror sync verifies :
 *  - eloquent.created server event creates the local Server row
 *  - eloquent.updated event syncs status changes (suspend / unsuspend)
 *  - eloquent.deleted event removes the local row
 *  - Unknown owner triggers a UserSync job and the server job is released
 *  - In Paymenter mode, ServerProvisioned event is NEVER fired (no email)
 *  - Reconciliation creates missing local rows on polling
 *  - Reconciliation removes orphan local rows that no longer exist Pelican-side
 */
class PelicanMirrorSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['key' => 'bridge_mode'], ['value' => BridgeMode::Paymenter->value]);
        app(SettingsService::class)->clearCache();
    }

    /**
     * Register a Pelican Application API mock whose `getServer()` returns the
     * canonical state used by `SyncServerFromPelicanWebhookJob`. Each test
     * needs this because the job refetches the server (the webhook payload
     * doesn't carry the suspended state directly).
     */
    private function mockPelicanGetServer(int $pelicanServerId, int $userId, int $eggId = 1, bool $isSuspended = false, string $name = 'srv', string $identifier = 'iden', ?string $status = null): \Mockery\MockInterface
    {
        $dto = new PelicanServer(
            id: $pelicanServerId,
            identifier: $identifier,
            name: $name,
            description: '',
            userId: $userId,
            nodeId: 1,
            eggId: $eggId,
            nestId: 0,
            isSuspended: $isSuspended,
            limits: new ServerLimits(memory: 1024, swap: 0, disk: 5000, io: 500, cpu: 100),
            status: $status,
        );

        $mock = Mockery::mock(PelicanApplicationService::class);
        $mock->shouldReceive('getServer')->with($pelicanServerId)->andReturn($dto);
        $this->app->instance(PelicanApplicationService::class, $mock);

        return $mock;
    }

    /**
     * Force Pelican API refetch to throw — exercises the fallback path where
     * the job uses the webhook payload as-is.
     */
    private function mockPelicanGetServerThrows(): void
    {
        $mock = Mockery::mock(PelicanApplicationService::class);
        $mock->shouldReceive('getServer')->andThrow(new \RuntimeException('pelican unreachable'));
        $this->app->instance(PelicanApplicationService::class, $mock);
    }

    public function test_server_created_event_creates_local_server_row(): void
    {
        $owner = User::factory()->create(['pelican_user_id' => 99]);
        $this->mockPelicanGetServer(42, 99, name: 'paymenter-test', identifier: 'abc12345');

        $job = new SyncServerFromPelicanWebhookJob(
            eventType: 'eloquent.created: App\\Models\\Server',
            pelicanServerId: 42,
            payloadSnapshot: [
                'id' => 42,
                'identifier' => 'abc12345',
                'name' => 'paymenter-test',
                'user' => 99,
                'node_id' => 1,
                'egg_id' => 1,
                'external_id' => 'pm-svc-7',
                'updated_at' => '2026-04-22 10:00:00',
            ],
        );
        $job->handle(app(PelicanApplicationService::class), app(\App\Services\Bridge\BridgeModeService::class));

        $this->assertDatabaseHas('servers', [
            'pelican_server_id' => 42,
            'user_id' => $owner->id,
            'name' => 'paymenter-test',
            'identifier' => 'abc12345',
            'status' => 'active',
            'paymenter_service_id' => 'pm-svc-7',
        ]);
    }

    public function test_server_updated_event_syncs_suspended_state_from_api(): void
    {
        $owner = User::factory()->create(['pelican_user_id' => 5]);
        $server = Server::create([
            'pelican_server_id' => 7,
            'user_id' => $owner->id,
            'name' => 'srv',
            'identifier' => 'iden',
            'status' => 'active',
        ]);

        // Pelican's webhook payload omits the suspended flag — it only ships
        // `status: null` when a server is suspended. The API call is the
        // ground truth and reports isSuspended=true.
        $this->mockPelicanGetServer(7, 5, isSuspended: true, name: 'srv', identifier: 'iden');

        $job = new SyncServerFromPelicanWebhookJob(
            eventType: 'eloquent.updated: App\\Models\\Server',
            pelicanServerId: 7,
            payloadSnapshot: [
                'id' => 7,
                'identifier' => 'iden',
                'name' => 'srv',
                'user' => 5,
                'status' => null,
                'updated_at' => '2026-04-22 10:00:00',
            ],
        );
        $job->handle(app(PelicanApplicationService::class), app(\App\Services\Bridge\BridgeModeService::class));

        $server->refresh();
        $this->assertSame('suspended', $server->status);
    }

    public function test_server_deleted_event_removes_local_row(): void
    {
        $owner = User::factory()->create(['pelican_user_id' => 5]);
        Server::create([
            'pelican_server_id' => 100,
            'user_id' => $owner->id,
            'name' => 'doomed',
            'identifier' => 'doom',
            'status' => 'active',
        ]);

        // Deletion event short-circuits before any API call — provide a no-op
        // mock so the container resolution still works.
        $mock = Mockery::mock(PelicanApplicationService::class);
        $this->app->instance(PelicanApplicationService::class, $mock);

        $job = new SyncServerFromPelicanWebhookJob(
            eventType: 'eloquent.deleted: App\\Models\\Server',
            pelicanServerId: 100,
            payloadSnapshot: ['id' => 100],
        );
        $job->handle($mock, app(\App\Services\Bridge\BridgeModeService::class));

        $this->assertDatabaseMissing('servers', ['pelican_server_id' => 100]);
    }

    public function test_install_lifecycle_starts_persists_provisioning_status(): void
    {
        // `created: Server` fires the moment Pelican inserts the row with
        // status=installing. Peregrine should mirror that as `provisioning`
        // so the UI can show "Installation en cours…" without polling.
        $owner = User::factory()->create(['pelican_user_id' => 7]);
        $this->mockPelicanGetServer(150, 7, name: 'fresh', identifier: 'fr3sh', status: 'installing');

        $job = new SyncServerFromPelicanWebhookJob(
            eventType: 'eloquent.created: App\\Models\\Server',
            pelicanServerId: 150,
            payloadSnapshot: [
                'id' => 150,
                'identifier' => 'fr3sh',
                'name' => 'fresh',
                'user' => 7,
                'status' => 'installing',
                'updated_at' => '2026-04-29 11:45:00',
            ],
        );
        $job->handle(app(PelicanApplicationService::class), app(\App\Services\Bridge\BridgeModeService::class));

        $this->assertDatabaseHas('servers', [
            'pelican_server_id' => 150,
            'status' => 'provisioning',
        ]);
    }

    public function test_install_lifecycle_completion_flips_provisioning_to_active(): void
    {
        // `event: Server\Installed` (successful) — Pelican has cleared the
        // installing status (DTO.status === null). The full-upsert path must
        // bring the local row to `active`.
        $owner = User::factory()->create(['pelican_user_id' => 7]);
        Server::create([
            'pelican_server_id' => 151,
            'user_id' => $owner->id,
            'name' => 'fresh',
            'identifier' => 'fr3sh',
            'status' => 'provisioning',
        ]);
        $this->mockPelicanGetServer(151, 7, name: 'fresh', identifier: 'fr3sh', status: null);

        $job = new SyncServerFromPelicanWebhookJob(
            eventType: 'event: Server\\Installed',
            pelicanServerId: 151,
            payloadSnapshot: [
                'id' => 151,
                'identifier' => 'fr3sh',
                'name' => 'fresh',
                'user' => 7,
                'status' => null,
                'installed_at' => '2026-04-29 11:50:00',
                'updated_at' => '2026-04-29 11:50:00',
            ],
        );
        $job->handle(app(PelicanApplicationService::class), app(\App\Services\Bridge\BridgeModeService::class));

        $this->assertDatabaseHas('servers', [
            'pelican_server_id' => 151,
            'status' => 'active',
        ]);
    }

    public function test_install_lifecycle_failure_flips_to_provisioning_failed(): void
    {
        // `event: Server\Installed` with successful=false — Pelican sets
        // server.status to `install_failed`. Peregrine mirrors that as
        // `provisioning_failed` so the UI can prompt for retry.
        $owner = User::factory()->create(['pelican_user_id' => 7]);
        Server::create([
            'pelican_server_id' => 152,
            'user_id' => $owner->id,
            'name' => 'broken',
            'identifier' => 'brk',
            'status' => 'provisioning',
        ]);
        $this->mockPelicanGetServer(152, 7, name: 'broken', identifier: 'brk', status: 'install_failed');

        $job = new SyncServerFromPelicanWebhookJob(
            eventType: 'event: Server\\Installed',
            pelicanServerId: 152,
            payloadSnapshot: [
                'id' => 152,
                'identifier' => 'brk',
                'name' => 'broken',
                'user' => 7,
                'status' => 'install_failed',
                'updated_at' => '2026-04-29 11:50:00',
            ],
        );
        $job->handle(app(PelicanApplicationService::class), app(\App\Services\Bridge\BridgeModeService::class));

        $this->assertDatabaseHas('servers', [
            'pelican_server_id' => 152,
            'status' => 'provisioning_failed',
        ]);
    }

    public function test_server_with_unknown_owner_dispatches_user_sync_first(): void
    {
        Bus::fake();

        // No local user with pelican_user_id=999 → API returns it as the owner,
        // job dispatches the user sync job and self-releases.
        $this->mockPelicanGetServer(200, 999, name: 'orphan-server', identifier: 'orphan');

        $job = new SyncServerFromPelicanWebhookJob(
            eventType: 'eloquent.created: App\\Models\\Server',
            pelicanServerId: 200,
            payloadSnapshot: [
                'id' => 200,
                'identifier' => 'orphan',
                'name' => 'orphan-server',
                'user' => 999,
                'updated_at' => '2026-04-22 10:00:00',
            ],
        );
        $job->handle(app(PelicanApplicationService::class), app(\App\Services\Bridge\BridgeModeService::class));

        Bus::assertDispatched(SyncUserFromPelicanWebhookJob::class, fn ($j) => $j->pelicanUserId === 999);
        $this->assertDatabaseMissing('servers', ['pelican_server_id' => 200]);
    }

    public function test_paymenter_mode_does_not_dispatch_server_provisioned_event(): void
    {
        Event::fake([ServerProvisioned::class]);

        $owner = User::factory()->create(['pelican_user_id' => 33]);
        $this->mockPelicanGetServer(88, 33, name: 'paymenter-server', identifier: 'p');

        $job = new SyncServerFromPelicanWebhookJob(
            eventType: 'eloquent.created: App\\Models\\Server',
            pelicanServerId: 88,
            payloadSnapshot: [
                'id' => 88,
                'identifier' => 'p',
                'name' => 'paymenter-server',
                'user' => 33,
                'updated_at' => '2026-04-22 10:00:00',
            ],
        );
        $job->handle(app(PelicanApplicationService::class), app(\App\Services\Bridge\BridgeModeService::class));

        Event::assertNotDispatched(ServerProvisioned::class);
    }

    public function test_reconciliation_creates_missing_local_server_via_polling(): void
    {
        Bus::fake();

        $owner = User::factory()->create(['pelican_user_id' => 1]);

        $missing = new PelicanServer(
            id: 555,
            identifier: 'missing-id',
            name: 'paymenter-only',
            description: '',
            userId: 1,
            nodeId: 1,
            eggId: 1,
            nestId: 0,
            isSuspended: false,
            limits: new ServerLimits(memory: 1024, swap: 0, disk: 5000, io: 500, cpu: 100),
        );

        $pelicanMock = Mockery::mock(PelicanApplicationService::class);
        $pelicanMock->shouldReceive('listServers')->andReturn([$missing]);
        $this->app->instance(PelicanApplicationService::class, $pelicanMock);

        $clientMock = Mockery::mock(PelicanClientService::class);
        $this->app->instance(PelicanClientService::class, $clientMock);

        (new SyncServerStatusJob)->handle($clientMock, app(\App\Services\Bridge\PelicanMirrorReconciler::class));

        Bus::assertDispatched(SyncServerFromPelicanWebhookJob::class, fn ($j) => $j->pelicanServerId === 555);
    }

    public function test_reconciliation_removes_orphan_local_server(): void
    {
        $owner = User::factory()->create(['pelican_user_id' => 1]);

        Server::create([
            'pelican_server_id' => 9999,
            'user_id' => $owner->id,
            'name' => 'orphan',
            'identifier' => 'orph',
            'status' => 'active',
        ]);

        // Pelican returns nothing — Server 9999 is orphan locally.
        $pelicanMock = Mockery::mock(PelicanApplicationService::class);
        $pelicanMock->shouldReceive('listServers')->andReturn([]);
        $this->app->instance(PelicanApplicationService::class, $pelicanMock);

        $clientMock = Mockery::mock(PelicanClientService::class);
        $this->app->instance(PelicanClientService::class, $clientMock);

        (new SyncServerStatusJob)->handle($clientMock, app(\App\Services\Bridge\PelicanMirrorReconciler::class));

        $this->assertDatabaseMissing('servers', ['pelican_server_id' => 9999]);
    }

    public function test_server_created_event_maps_egg_id_when_egg_mirror_exists(): void
    {
        $owner = User::factory()->create(['pelican_user_id' => 5]);
        // Local egg has its own auto-incremented `id`, but its `pelican_egg_id`
        // matches the value Pelican sends in the webhook `egg_id` field.
        $egg = \App\Models\Egg::create(['pelican_egg_id' => 42, 'name' => 'minecraft', 'description' => '', 'docker_image' => 'pelican/yolks:java_17', 'startup' => 'java -jar server.jar']);
        $this->mockPelicanGetServer(200, 5, eggId: 42, name: 'egg-test', identifier: 'eggtest');

        $job = new SyncServerFromPelicanWebhookJob(
            eventType: 'eloquent.created: App\\Models\\Server',
            pelicanServerId: 200,
            payloadSnapshot: [
                'id' => 200,
                'identifier' => 'eggtest',
                'name' => 'egg-test',
                'user' => 5,
                'egg_id' => 42, // Pelican-side egg id, NOT the local PK
                'updated_at' => '2026-04-22 10:00:00',
            ],
        );
        $job->handle(app(PelicanApplicationService::class), app(\App\Services\Bridge\BridgeModeService::class));

        $this->assertDatabaseHas('servers', [
            'pelican_server_id' => 200,
            'egg_id' => $egg->id,
        ]);
    }

    public function test_server_created_event_leaves_egg_id_null_when_no_mirror_and_auto_sync_fails(): void
    {
        // Force both the egg auto-sync AND the Pelican getServer refetch to
        // fail (covers the worst-case fallback path). The job still creates
        // the server row using the webhook payload's egg_id, but cannot
        // resolve it to a local egg, so egg_id ends up null.
        $infraMock = \Mockery::mock(\App\Services\Sync\InfrastructureSync::class);
        $infraMock->shouldReceive('syncEggs')->andThrow(new \RuntimeException('pelican unreachable'));
        $this->app->instance(\App\Services\Sync\InfrastructureSync::class, $infraMock);

        $this->mockPelicanGetServerThrows();

        $owner = User::factory()->create(['pelican_user_id' => 5]);

        $job = new SyncServerFromPelicanWebhookJob(
            eventType: 'eloquent.created: App\\Models\\Server',
            pelicanServerId: 201,
            payloadSnapshot: [
                'id' => 201,
                'identifier' => 'noegg',
                'name' => 'no-egg',
                'user' => 5,
                'egg_id' => 999, // No matching pelican_egg_id locally
                'updated_at' => '2026-04-22 10:00:00',
            ],
        );
        $job->handle(app(PelicanApplicationService::class), app(\App\Services\Bridge\BridgeModeService::class));

        $this->assertDatabaseHas('servers', [
            'pelican_server_id' => 201,
            'egg_id' => null,
        ]);
    }

    public function test_user_sync_job_creates_local_user(): void
    {
        $pelicanUser = new PelicanUser(
            id: 77,
            email: 'paymenter-user@example.com',
            username: 'paymenter-user',
            name: 'Paymenter User',
            isAdmin: false,
            createdAt: '2026-04-22T10:00:00Z',
        );

        $pelicanMock = Mockery::mock(PelicanApplicationService::class);
        $pelicanMock->shouldReceive('getUser')->with(77)->andReturn($pelicanUser);
        $this->app->instance(PelicanApplicationService::class, $pelicanMock);

        (new SyncUserFromPelicanWebhookJob(77))->handle($pelicanMock);

        $this->assertDatabaseHas('users', [
            'pelican_user_id' => 77,
            'email' => 'paymenter-user@example.com',
        ]);
    }
}
