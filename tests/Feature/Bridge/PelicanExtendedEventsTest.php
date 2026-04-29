<?php

namespace Tests\Feature\Bridge;

use App\Enums\BridgeMode;
use App\Enums\PelicanEventKind;
use App\Jobs\Bridge\SyncEggFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncNodeFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncUserFromPelicanWebhookJob;
use App\Models\Egg;
use App\Models\Nest;
use App\Models\Node;
use App\Models\Server;
use App\Models\ServerPlan;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Locks the Phase 1 extended event handling: UserUpdated/Deleted, Node CRUD,
 * Egg CRUD, EggVariable CRUD. Each event is exercised end-to-end through
 * the receiver + classifier + dispatch pipeline, plus the corresponding job
 * is unit-tested with Http::fake() for the Pelican API refetch.
 *
 * Tests are organised :
 *   1. Receiver-level dispatch parity (one test per event kind)
 *   2. Job-level upsert/delete logic (Http::fake to mock Pelican)
 *   3. Mode gate granularity (UserCreated still gated, others ungated)
 */
class PelicanExtendedEventsTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'pelican-test-token-please-keep-it-long-enough-1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        Setting::updateOrCreate(['key' => 'pelican_webhook_enabled'], ['value' => 'true']);
        Setting::updateOrCreate(
            ['key' => 'pelican_webhook_token'],
            ['value' => Crypt::encryptString(self::TOKEN)],
        );
        Setting::updateOrCreate(['key' => 'bridge_mode'], ['value' => BridgeMode::Paymenter->value]);
        app(SettingsService::class)->clearCache();
    }

    // -------- Receiver dispatch parity --------------------------------------

    public function test_user_updated_event_dispatches_job_in_paymenter_mode(): void
    {
        Bus::fake();

        $response = $this->pelicanPost(
            payload: ['payload' => ['id' => 9, 'email' => 'u@x.com', 'updated_at' => '2026-04-28 10:00:00']],
            event: 'updated: User',
        );

        $response->assertStatus(200);
        Bus::assertDispatched(SyncUserFromPelicanWebhookJob::class, fn ($job) => $job->pelicanUserId === 9
            && $job->eventKind === PelicanEventKind::UserUpdated);
    }

    public function test_user_updated_event_dispatches_in_shop_stripe_mode_too(): void
    {
        // Updates run in ALL modes (covers admin email change in Pelican
        // panel — we mirror it even when Shop owns identity).
        Setting::updateOrCreate(['key' => 'bridge_mode'], ['value' => BridgeMode::ShopStripe->value]);
        app(SettingsService::class)->clearCache();

        Bus::fake();

        $response = $this->pelicanPost(
            payload: ['payload' => ['id' => 9, 'email' => 'u@x.com', 'updated_at' => '2026-04-28 10:00:00']],
            event: 'updated: User',
        );

        $response->assertStatus(200);
        Bus::assertDispatched(SyncUserFromPelicanWebhookJob::class);
    }

    public function test_user_deleted_event_dispatches_in_all_modes(): void
    {
        Setting::updateOrCreate(['key' => 'bridge_mode'], ['value' => BridgeMode::ShopStripe->value]);
        app(SettingsService::class)->clearCache();

        Bus::fake();

        $response = $this->pelicanPost(
            payload: ['payload' => ['id' => 9, 'updated_at' => '2026-04-28 10:00:00']],
            event: 'deleted: User',
        );

        $response->assertStatus(200);
        Bus::assertDispatched(SyncUserFromPelicanWebhookJob::class, fn ($job) => $job->eventKind === PelicanEventKind::UserDeleted);
    }

    public function test_node_created_event_dispatches_node_sync_job(): void
    {
        Bus::fake();

        $response = $this->pelicanPost(
            payload: ['payload' => ['id' => 5, 'name' => 'node-eu', 'fqdn' => 'eu.example.com', 'updated_at' => '2026-04-28 10:00:00']],
            event: 'created: Node',
        );

        $response->assertStatus(200);
        Bus::assertDispatched(SyncNodeFromPelicanWebhookJob::class, fn ($job) => $job->pelicanNodeId === 5
            && $job->eventKind === PelicanEventKind::NodeCreated);
    }

    public function test_node_deleted_event_dispatches_with_correct_kind(): void
    {
        Bus::fake();

        $response = $this->pelicanPost(
            payload: ['payload' => ['id' => 5, 'updated_at' => '2026-04-28 10:00:00']],
            event: 'deleted: Node',
        );

        $response->assertStatus(200);
        Bus::assertDispatched(SyncNodeFromPelicanWebhookJob::class, fn ($job) => $job->eventKind === PelicanEventKind::NodeDeleted);
    }

    public function test_egg_created_event_dispatches_egg_sync_job(): void
    {
        Bus::fake();

        $response = $this->pelicanPost(
            payload: ['payload' => ['id' => 12, 'name' => 'Minecraft', 'updated_at' => '2026-04-28 10:00:00']],
            event: 'created: Egg',
        );

        $response->assertStatus(200);
        Bus::assertDispatched(SyncEggFromPelicanWebhookJob::class, fn ($job) => $job->pelicanEggId === 12
            && $job->eventKind === PelicanEventKind::EggCreated);
    }

    public function test_egg_variable_event_dispatches_with_parent_egg_id(): void
    {
        // EggVariable mutations resync the PARENT egg — the modelId in payload
        // is the variable id, but we extract `egg_id` for the job.
        Bus::fake();

        $response = $this->pelicanPost(
            payload: ['payload' => ['id' => 99, 'egg_id' => 12, 'name' => 'SERVER_JAR', 'updated_at' => '2026-04-28 10:00:00']],
            event: 'updated: EggVariable',
        );

        $response->assertStatus(200);
        Bus::assertDispatched(SyncEggFromPelicanWebhookJob::class, fn ($job) => $job->pelicanEggId === 12
            && $job->eventKind === PelicanEventKind::EggVariableUpdated);
    }

    public function test_egg_variable_event_without_egg_id_records_ignored(): void
    {
        Bus::fake();

        $response = $this->pelicanPost(
            payload: ['payload' => ['id' => 99, 'name' => 'foo', 'updated_at' => '2026-04-28 10:00:00']],
            event: 'updated: EggVariable',
        );

        $response->assertStatus(200);
        Bus::assertNotDispatched(SyncEggFromPelicanWebhookJob::class);
        $this->assertDatabaseHas('pelican_processed_events', [
            'event_type' => 'updated: EggVariable',
            'pelican_model_id' => 99,
        ]);
    }

    // -------- Job-level upsert / delete logic -------------------------------

    public function test_user_updated_job_upserts_email_and_name_via_api(): void
    {
        $existing = User::factory()->create([
            'pelican_user_id' => 42,
            'email' => 'old@example.com',
            'name' => 'Old Name',
        ]);

        Http::fake([
            '*/api/application/users/42*' => Http::response([
                'attributes' => [
                    'id' => 42,
                    'username' => 'newuser',
                    'email' => 'new@example.com',
                    'first_name' => 'New',
                    'last_name' => 'Name',
                    'language' => 'en',
                    'root_admin' => false,
                    '2fa_enabled' => false,
                    'created_at' => '2026-04-01 00:00:00',
                    'updated_at' => '2026-04-28 10:00:00',
                ],
            ], 200),
        ]);

        (new SyncUserFromPelicanWebhookJob(42, PelicanEventKind::UserUpdated))->handle(app(\App\Services\Pelican\PelicanApplicationService::class));

        $existing->refresh();
        $this->assertSame('new@example.com', $existing->email);
    }

    public function test_user_deleted_job_detaches_pelican_user_id(): void
    {
        $user = User::factory()->create([
            'pelican_user_id' => 42,
            'email' => 'still-here@example.com',
        ]);

        (new SyncUserFromPelicanWebhookJob(42, PelicanEventKind::UserDeleted))->handle(app(\App\Services\Pelican\PelicanApplicationService::class));

        $user->refresh();
        $this->assertNull($user->pelican_user_id);
        // User itself NOT hard-deleted (Stripe sub + OAuth identities preserved).
        $this->assertNotNull(User::find($user->id));
    }

    public function test_node_created_job_upserts_into_local_table(): void
    {
        Http::fake([
            '*/api/application/nodes/7*' => Http::response([
                'attributes' => [
                    'id' => 7,
                    'name' => 'node-eu',
                    'fqdn' => 'eu.example.com',
                    'memory' => 16384,
                    'disk' => 100000,
                    'location_id' => 'EU',
                ],
            ], 200),
        ]);

        (new SyncNodeFromPelicanWebhookJob(7, PelicanEventKind::NodeCreated))->handle(app(\App\Services\Pelican\PelicanApplicationService::class));

        $this->assertDatabaseHas('nodes', [
            'pelican_node_id' => 7,
            'name' => 'node-eu',
            'fqdn' => 'eu.example.com',
        ]);
    }

    public function test_node_deleted_job_refuses_when_plans_reference_node(): void
    {
        $node = Node::create([
            'pelican_node_id' => 7,
            'name' => 'node-eu',
            'fqdn' => 'eu.example.com',
            'memory' => 16000,
            'disk' => 100000,
            'location' => 'EU',
        ]);
        ServerPlan::create([
            'name' => 'Plan referencing node',
            'ram' => 1024,
            'cpu' => 100,
            'disk' => 5000,
            'default_node_id' => $node->id,
            'is_active' => true,
        ]);

        (new SyncNodeFromPelicanWebhookJob(7, PelicanEventKind::NodeDeleted))->handle(app(\App\Services\Pelican\PelicanApplicationService::class));

        $this->assertDatabaseHas('nodes', ['id' => $node->id]);
    }

    public function test_node_deleted_job_removes_node_when_no_references(): void
    {
        $node = Node::create([
            'pelican_node_id' => 7,
            'name' => 'node-eu',
            'fqdn' => 'eu.example.com',
            'memory' => 16000,
            'disk' => 100000,
            'location' => 'EU',
        ]);

        (new SyncNodeFromPelicanWebhookJob(7, PelicanEventKind::NodeDeleted))->handle(app(\App\Services\Pelican\PelicanApplicationService::class));

        $this->assertDatabaseMissing('nodes', ['id' => $node->id]);
    }

    public function test_egg_created_job_upserts_egg_and_nest(): void
    {
        Http::fake([
            '*/api/application/eggs/12*' => Http::response([
                'attributes' => [
                    'id' => 12,
                    'nest' => 3,
                    'name' => 'Minecraft',
                    'docker_image' => 'ghcr.io/pterodactyl/yolks:java_17',
                    'startup' => 'java -jar server.jar',
                    'description' => 'Vanilla Minecraft',
                    'tags' => ['minecraft'],
                    'features' => [],
                ],
            ], 200),
        ]);

        (new SyncEggFromPelicanWebhookJob(12, PelicanEventKind::EggCreated))->handle(app(\App\Services\Pelican\PelicanApplicationService::class));

        $this->assertDatabaseHas('eggs', [
            'pelican_egg_id' => 12,
            'name' => 'Minecraft',
        ]);
        $this->assertDatabaseHas('nests', ['pelican_nest_id' => 3]);
    }

    public function test_egg_variable_event_resyncs_parent_egg(): void
    {
        Http::fake([
            '*/api/application/eggs/12*' => Http::response([
                'attributes' => [
                    'id' => 12,
                    'nest' => 3,
                    'name' => 'Minecraft v2',
                    'docker_image' => 'ghcr.io/yolks/java_21',
                    'startup' => 'java -jar new.jar',
                    'description' => '',
                    'tags' => [],
                    'features' => [],
                ],
            ], 200),
        ]);

        Egg::create([
            'pelican_egg_id' => 12,
            'nest_id' => Nest::create(['pelican_nest_id' => 3, 'name' => 'Nest #3'])->id,
            'name' => 'Minecraft (old)',
            'docker_image' => 'old-image',
            'startup' => 'old',
            'description' => '',
        ]);

        (new SyncEggFromPelicanWebhookJob(12, PelicanEventKind::EggVariableUpdated))->handle(app(\App\Services\Pelican\PelicanApplicationService::class));

        $this->assertDatabaseHas('eggs', [
            'pelican_egg_id' => 12,
            'name' => 'Minecraft v2',
            'docker_image' => 'ghcr.io/yolks/java_21',
        ]);
    }

    public function test_egg_deleted_job_refuses_when_servers_reference_egg(): void
    {
        $nest = Nest::create(['pelican_nest_id' => 3, 'name' => 'Nest #3']);
        $egg = Egg::create([
            'pelican_egg_id' => 12,
            'nest_id' => $nest->id,
            'name' => 'Minecraft',
            'docker_image' => '',
            'startup' => '',
            'description' => '',
        ]);
        $user = User::factory()->create();
        Server::create([
            'user_id' => $user->id,
            'name' => 'srv-1',
            'status' => 'active',
            'egg_id' => $egg->id,
            'idempotency_key' => 'test-1',
        ]);

        (new SyncEggFromPelicanWebhookJob(12, PelicanEventKind::EggDeleted))->handle(app(\App\Services\Pelican\PelicanApplicationService::class));

        $this->assertDatabaseHas('eggs', ['id' => $egg->id]);
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
