<?php

namespace Tests\Feature\Bridge;

use App\Enums\BridgeMode;
use App\Enums\PelicanEventKind;
use App\Events\Bridge\SubuserSynced;
use App\Jobs\Bridge\DispatchSubuserSyncedJob;
use App\Jobs\Bridge\SyncAllocationFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncBackupFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncDatabaseFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncDatabaseHostFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncServerTransferFromPelicanWebhookJob;
use App\Models\Node;
use App\Models\Pelican\Allocation;
use App\Models\Pelican\Backup;
use App\Models\Pelican\Database as PelicanDatabase;
use App\Models\Pelican\DatabaseHost;
use App\Models\Pelican\ServerTransfer;
use App\Models\Server;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Phase 2 mirror end-to-end : webhook receiver dispatches the correct job
 * for each new resource type, the job persists into the mirror table,
 * controllers serve from the mirror when mirror_reads_enabled is on,
 * and the security canary catches password fields if Pelican ever leaks.
 */
class PelicanMirrorTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'pelican-test-token-please-keep-it-long-enough-1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        Setting::updateOrCreate(['key' => 'pelican_webhook_enabled'], ['value' => 'true']);
        Setting::updateOrCreate(['key' => 'pelican_webhook_token'], ['value' => Crypt::encryptString(self::TOKEN)]);
        Setting::updateOrCreate(['key' => 'bridge_mode'], ['value' => BridgeMode::Paymenter->value]);
        app(SettingsService::class)->clearCache();
    }

    // -------- Receiver dispatch parity --------------------------------------

    public function test_backup_created_event_dispatches_backup_sync(): void
    {
        Bus::fake();
        $response = $this->pelicanPost(
            payload: ['payload' => ['id' => 5, 'server_id' => 99, 'name' => 'snap', 'updated_at' => '2026-04-29 10:00:00']],
            event: 'created: Backup',
        );
        $response->assertStatus(200);
        Bus::assertDispatched(SyncBackupFromPelicanWebhookJob::class, fn ($j) => $j->pelicanBackupId === 5
            && $j->pelicanServerId === 99);
    }

    public function test_backup_event_without_server_id_is_ignored(): void
    {
        Bus::fake();
        $response = $this->pelicanPost(
            payload: ['payload' => ['id' => 5, 'updated_at' => '2026-04-29 10:00:00']],
            event: 'created: Backup',
        );
        $response->assertStatus(200);
        Bus::assertNotDispatched(SyncBackupFromPelicanWebhookJob::class);
    }

    public function test_allocation_event_dispatches_allocation_sync(): void
    {
        Bus::fake();
        $response = $this->pelicanPost(
            payload: ['payload' => ['id' => 7, 'ip' => '1.2.3.4', 'port' => 25565, 'updated_at' => '2026-04-29 10:00:00']],
            event: 'updated: Allocation',
        );
        $response->assertStatus(200);
        Bus::assertDispatched(SyncAllocationFromPelicanWebhookJob::class);
    }

    public function test_database_event_dispatches_database_sync(): void
    {
        Bus::fake();
        $response = $this->pelicanPost(
            payload: ['payload' => ['id' => 11, 'server_id' => 99, 'database' => 's5_main', 'username' => 'u5', 'updated_at' => '2026-04-29 10:00:00']],
            event: 'created: Database',
        );
        $response->assertStatus(200);
        Bus::assertDispatched(SyncDatabaseFromPelicanWebhookJob::class);
    }

    public function test_database_host_event_dispatches_host_sync(): void
    {
        Bus::fake();
        $response = $this->pelicanPost(
            payload: ['payload' => ['id' => 1, 'name' => 'main', 'host' => 'db.example.com', 'port' => 3306, 'username' => 'admin', 'updated_at' => '2026-04-29 10:00:00']],
            event: 'created: DatabaseHost',
        );
        $response->assertStatus(200);
        Bus::assertDispatched(SyncDatabaseHostFromPelicanWebhookJob::class);
    }

    public function test_server_transfer_event_dispatches_transfer_sync(): void
    {
        Bus::fake();
        $response = $this->pelicanPost(
            payload: ['payload' => ['id' => 2, 'server_id' => 99, 'archived' => false, 'updated_at' => '2026-04-29 10:00:00']],
            event: 'created: ServerTransfer',
        );
        $response->assertStatus(200);
        Bus::assertDispatched(SyncServerTransferFromPelicanWebhookJob::class);
    }

    public function test_subuser_event_dispatches_subuser_synced_job(): void
    {
        Bus::fake();
        $response = $this->pelicanPost(
            payload: ['payload' => ['id' => 3, 'server_id' => 99, 'user_id' => 7, 'updated_at' => '2026-04-29 10:00:00']],
            event: 'event: Server\\SubUserAdded',
        );
        $response->assertStatus(200);
        Bus::assertDispatched(DispatchSubuserSyncedJob::class);
    }

    // -------- Job-level upsert ----------------------------------------------

    public function test_backup_job_upserts_into_local_table(): void
    {
        $user = User::factory()->create();
        $server = Server::create([
            'user_id' => $user->id, 'name' => 's', 'status' => 'active',
            'pelican_server_id' => 50, 'idempotency_key' => 'k50',
        ]);

        (new SyncBackupFromPelicanWebhookJob(
            pelicanBackupId: 1,
            pelicanServerId: 50,
            payload: [
                'uuid' => 'uuid-1', 'name' => 'first',
                'is_successful' => true, 'bytes' => 1024,
                'completed_at' => '2026-04-29 10:00:00',
            ],
            eventKind: PelicanEventKind::BackupUpdated,
        ))->handle();

        $this->assertDatabaseHas('pelican_backups', [
            'pelican_backup_id' => 1, 'server_id' => $server->id,
            'name' => 'first', 'is_successful' => 1,
        ]);
    }

    public function test_allocation_job_upserts_with_node_lookup(): void
    {
        $node = Node::create([
            'pelican_node_id' => 7, 'name' => 'eu', 'fqdn' => 'eu.example',
            'memory' => 0, 'disk' => 0, 'location' => '',
        ]);

        (new SyncAllocationFromPelicanWebhookJob(
            pelicanAllocationId: 100,
            payload: ['node_id' => 7, 'ip' => '1.2.3.4', 'port' => 25565],
            eventKind: PelicanEventKind::AllocationCreated,
        ))->handle();

        $this->assertDatabaseHas('pelican_allocations', [
            'pelican_allocation_id' => 100, 'node_id' => $node->id,
            'ip' => '1.2.3.4', 'port' => 25565,
        ]);
    }

    public function test_database_job_never_persists_password(): void
    {
        $user = User::factory()->create();
        $server = Server::create([
            'user_id' => $user->id, 'name' => 's', 'status' => 'active',
            'pelican_server_id' => 51, 'idempotency_key' => 'k51',
        ]);

        Log::spy();

        (new SyncDatabaseFromPelicanWebhookJob(
            pelicanDatabaseId: 1,
            pelicanServerId: 51,
            payload: [
                'database' => 's5_main', 'username' => 'u5',
                // simulated regression: Pelican leaks a password (canary should fire)
                'password' => 'super-secret',
            ],
            eventKind: PelicanEventKind::DatabaseCreated,
        ))->handle();

        Log::shouldHaveReceived('critical')->once();
        $this->assertDatabaseHas('pelican_databases', [
            'pelican_database_id' => 1, 'database' => 's5_main',
        ]);
        // Password is NOT a column → can't even leak by accident.
        $row = PelicanDatabase::where('pelican_database_id', 1)->first();
        $this->assertNotNull($row);
        $this->assertArrayNotHasKey('password', $row->getAttributes());
    }

    public function test_subuser_dispatch_fires_event_for_plugin(): void
    {
        Event::fake();

        (new DispatchSubuserSyncedJob(
            eventKind: PelicanEventKind::SubuserAdded,
            pelicanSubuserId: 5,
            payload: ['server_id' => 99, 'user_id' => 7, 'permissions' => ['power.start']],
        ))->handle();

        Event::assertDispatched(SubuserSynced::class, fn (SubuserSynced $e) => $e->pelicanServerId === 99
            && $e->pelicanUserId === 7
            && $e->eventKind === PelicanEventKind::SubuserAdded);
    }

    public function test_subuser_dispatch_skips_when_payload_missing_ids(): void
    {
        Event::fake();
        (new DispatchSubuserSyncedJob(
            eventKind: PelicanEventKind::SubuserAdded,
            pelicanSubuserId: 5,
            payload: [],
        ))->handle();
        Event::assertNotDispatched(SubuserSynced::class);
    }

    // -------- Controller refactor (mirror_reads_enabled flag) ---------------

    public function test_backup_controller_reads_from_mirror_when_flag_on(): void
    {
        $user = User::factory()->create();
        $server = Server::create([
            'user_id' => $user->id, 'name' => 's', 'status' => 'active',
            'pelican_server_id' => 60, 'identifier' => 'srvabcd',
            'idempotency_key' => 'k60',
        ]);
        $server->accessUsers()->syncWithoutDetaching([
            $user->id => ['role' => 'owner', 'permissions' => null],
        ]);

        Backup::create([
            'pelican_backup_id' => 1, 'server_id' => $server->id,
            'uuid' => 'u1', 'name' => 'localbk', 'is_successful' => true,
            'is_locked' => false, 'bytes' => 0,
            'pelican_created_at' => now()->subMinute(),
        ]);

        Setting::updateOrCreate(['key' => 'mirror_reads_enabled'], ['value' => 'true']);
        app(SettingsService::class)->clearCache();

        $this->actingAs($user)
            ->getJson("/api/servers/{$server->id}/backups")
            ->assertStatus(200)
            ->assertJsonPath('data.0.name', 'localbk');
    }

    // -------- Helpers --------------------------------------------------------

    /**
     * @param  array<string, mixed>  $payload
     */
    private function pelicanPost(array $payload, string $event): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/pelican/webhook', $payload, [
            'Authorization' => 'Bearer '.self::TOKEN,
            'X-Webhook-Event' => $event,
        ]);
    }
}
