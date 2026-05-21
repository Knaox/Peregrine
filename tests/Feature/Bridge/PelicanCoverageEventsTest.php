<?php

declare(strict_types=1);

namespace Tests\Feature\Bridge;

use App\Enums\PelicanEventKind;
use App\Jobs\Bridge\SyncEggFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncNodeFromPelicanWebhookJob;
use App\Models\Egg;
use App\Models\Nest;
use App\Models\Setting;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Fills the remaining gaps in event coverage so all 16 CRUD events have at
 * least one test : updated:Node, updated:Egg, created:EggVariable and
 * deleted:EggVariable were previously unexercised (Server / User / created+
 * deleted Node-Egg / updated EggVariable already live in their own suites).
 */
class PelicanCoverageEventsTest extends TestCase
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
        Setting::query()->where('key', 'bridge_stripe_webhook_secret')->delete();
        app(SettingsService::class)->clearCache();
    }

    // -------- Receiver dispatch parity --------------------------------------

    public function test_node_updated_event_dispatches_node_sync_job(): void
    {
        Bus::fake();

        $response = $this->pelicanPost(
            payload: ['payload' => ['id' => 5, 'name' => 'node-eu', 'updated_at' => '2026-05-21 10:00:00']],
            event: 'updated: Node',
        );

        $response->assertStatus(200);
        Bus::assertDispatched(SyncNodeFromPelicanWebhookJob::class, fn ($job) => $job->pelicanNodeId === 5
            && $job->eventKind === PelicanEventKind::NodeUpdated);
    }

    public function test_egg_updated_event_dispatches_egg_sync_job(): void
    {
        Bus::fake();

        $response = $this->pelicanPost(
            payload: ['payload' => ['id' => 12, 'name' => 'Minecraft', 'updated_at' => '2026-05-21 10:00:00']],
            event: 'updated: Egg',
        );

        $response->assertStatus(200);
        Bus::assertDispatched(SyncEggFromPelicanWebhookJob::class, fn ($job) => $job->pelicanEggId === 12
            && $job->eventKind === PelicanEventKind::EggUpdated);
    }

    public function test_egg_variable_created_event_dispatches_parent_egg_sync(): void
    {
        Bus::fake();

        $response = $this->pelicanPost(
            payload: ['payload' => ['id' => 99, 'egg_id' => 12, 'name' => 'SERVER_JAR', 'updated_at' => '2026-05-21 10:00:00']],
            event: 'created: EggVariable',
        );

        $response->assertStatus(200);
        Bus::assertDispatched(SyncEggFromPelicanWebhookJob::class, fn ($job) => $job->pelicanEggId === 12
            && $job->eventKind === PelicanEventKind::EggVariableCreated);
    }

    public function test_egg_variable_deleted_event_dispatches_parent_egg_sync(): void
    {
        Bus::fake();

        $response = $this->pelicanPost(
            payload: ['payload' => ['id' => 99, 'egg_id' => 12, 'name' => 'SERVER_JAR', 'updated_at' => '2026-05-21 10:00:00']],
            event: 'deleted: EggVariable',
        );

        $response->assertStatus(200);
        Bus::assertDispatched(SyncEggFromPelicanWebhookJob::class, fn ($job) => $job->pelicanEggId === 12
            && $job->eventKind === PelicanEventKind::EggVariableDeleted);
    }

    // -------- Job-level logic -----------------------------------------------

    public function test_node_updated_job_upserts_into_local_table(): void
    {
        Http::fake([
            '*/api/application/nodes/5*' => Http::response([
                'attributes' => [
                    'id' => 5,
                    'name' => 'node-eu-renamed',
                    'fqdn' => 'eu2.example.com',
                    'memory' => 32768,
                    'disk' => 200000,
                    'location_id' => 'EU',
                ],
            ], 200),
        ]);

        (new SyncNodeFromPelicanWebhookJob(5, PelicanEventKind::NodeUpdated))
            ->handle(app(PelicanApplicationService::class));

        $this->assertDatabaseHas('nodes', [
            'pelican_node_id' => 5,
            'name' => 'node-eu-renamed',
            'fqdn' => 'eu2.example.com',
        ]);
    }

    public function test_egg_updated_job_refetches_and_upserts_egg(): void
    {
        Http::fake([
            '*/api/application/eggs/12*' => Http::response([
                'attributes' => [
                    'id' => 12,
                    'nest' => 3,
                    'name' => 'Minecraft v2',
                    'docker_image' => 'ghcr.io/yolks/java_21',
                    'startup' => 'java -jar new.jar',
                    'description' => 'updated',
                    'tags' => [],
                    'features' => [],
                ],
            ], 200),
        ]);

        (new SyncEggFromPelicanWebhookJob(12, PelicanEventKind::EggUpdated))
            ->handle(app(PelicanApplicationService::class));

        $this->assertDatabaseHas('eggs', [
            'pelican_egg_id' => 12,
            'name' => 'Minecraft v2',
            'docker_image' => 'ghcr.io/yolks/java_21',
        ]);
    }

    public function test_egg_variable_deleted_job_resyncs_parent_without_deleting_egg(): void
    {
        $nest = Nest::create(['pelican_nest_id' => 3, 'name' => 'Nest #3']);
        Egg::create([
            'pelican_egg_id' => 12,
            'nest_id' => $nest->id,
            'name' => 'Minecraft (old)',
            'docker_image' => 'old-image',
            'startup' => 'old',
            'description' => '',
        ]);

        Http::fake([
            '*/api/application/eggs/12*' => Http::response([
                'attributes' => [
                    'id' => 12,
                    'nest' => 3,
                    'name' => 'Minecraft (var removed)',
                    'docker_image' => 'old-image',
                    'startup' => 'old',
                    'description' => '',
                    'tags' => [],
                    'features' => [],
                ],
            ], 200),
        ]);

        (new SyncEggFromPelicanWebhookJob(12, PelicanEventKind::EggVariableDeleted))
            ->handle(app(PelicanApplicationService::class));

        // The egg is refetched/upserted (not deleted) on a variable deletion.
        $this->assertDatabaseHas('eggs', [
            'pelican_egg_id' => 12,
            'name' => 'Minecraft (var removed)',
        ]);
    }

    // -------- Helpers --------------------------------------------------------

    /**
     * @param  array<string, mixed>  $payload
     */
    private function pelicanPost(array $payload, string $event): TestResponse
    {
        return $this->postJson('/api/pelican/webhook', $payload, [
            'Authorization' => 'Bearer '.self::TOKEN,
            'X-Webhook-Event' => $event,
        ]);
    }
}
