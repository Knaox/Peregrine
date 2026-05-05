<?php

namespace Tests\Feature;

use App\Enums\BridgeMode;
use App\Jobs\Bridge\ReconcilePelicanMirrorJob;
use App\Jobs\Bridge\SyncServerFromPelicanWebhookJob;
use App\Jobs\Bridge\SyncUserFromPelicanWebhookJob;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Locks the Pelican webhook contract end-to-end :
 *  - Bearer token validation (no native HMAC available)
 *  - Receiver toggle (`pelican_webhook_enabled`) — independent of Bridge mode
 *  - Idempotency by sha256(event|model_id|updated_at|body)
 *  - Dispatches SyncServerFromPelicanWebhookJob for Server eloquent events
 *  - Dispatches SyncUserFromPelicanWebhookJob for User created events
 *    (but only outside shop_stripe mode — see PelicanWebhookUserGateTest)
 *  - Unknown event types return 200 without side effect
 */
class PelicanWebhookTest extends TestCase
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
        // Default these tests to Paymenter mode so user-creation events are
        // still dispatched (shop_stripe explicitly skips them — covered in
        // its own test). The webhook itself is mode-agnostic now.
        Setting::updateOrCreate(['key' => 'bridge_mode'], ['value' => BridgeMode::Paymenter->value]);
        app(SettingsService::class)->clearCache();
    }

    public function test_rejects_invalid_token(): void
    {
        Bus::fake();

        $response = $this->pelicanPost(
            payload: $this->serverCreatedPayload(),
            token: 'wrong-token',
        );

        $response->assertStatus(401);
        Bus::assertNothingDispatched();
    }

    public function test_rejects_when_webhook_disabled(): void
    {
        Setting::updateOrCreate(['key' => 'pelican_webhook_enabled'], ['value' => 'false']);
        app(SettingsService::class)->clearCache();

        Bus::fake();

        $response = $this->pelicanPost($this->serverCreatedPayload());

        $response->assertStatus(503);
        Bus::assertNothingDispatched();
    }

    public function test_accepts_in_shop_stripe_mode_when_webhook_enabled(): void
    {
        // Whole point of the refactor: webhook now works in any Bridge mode
        // as long as it's enabled.
        Setting::updateOrCreate(['key' => 'bridge_mode'], ['value' => BridgeMode::ShopStripe->value]);
        app(SettingsService::class)->clearCache();

        Bus::fake();

        $response = $this->pelicanPost($this->serverCreatedPayload(pelicanServerId: 11));

        $response->assertStatus(200);
        Bus::assertDispatched(SyncServerFromPelicanWebhookJob::class, fn ($job) => $job->pelicanServerId === 11);
    }

    public function test_dispatches_server_sync_job_on_eloquent_created_server(): void
    {
        Bus::fake();

        $payload = $this->serverCreatedPayload(pelicanServerId: 42, externalId: '1234');
        $response = $this->pelicanPost($payload);

        $response->assertStatus(200);
        Bus::assertDispatched(SyncServerFromPelicanWebhookJob::class, function ($job): bool {
            return $job->pelicanServerId === 42
                && str_contains($job->eventType, 'eloquent.created')
                && ($job->payloadSnapshot['external_id'] ?? null) === '1234';
        });
        $this->assertDatabaseHas('pelican_processed_events', [
            'event_type' => 'eloquent.created: App\\Models\\Server',
            'pelican_model_id' => 42,
            'response_status' => 200,
        ]);
    }

    public function test_dispatches_user_sync_job_on_eloquent_created_user_in_paymenter_mode(): void
    {
        Bus::fake();

        $payload = [
            'event' => 'eloquent.created: App\\Models\\User',
            'data' => ['id' => 7, 'email' => 'pelican-user@example.com', 'updated_at' => '2026-04-22 10:00:00'],
        ];

        $response = $this->pelicanPost($payload);

        $response->assertStatus(200);
        Bus::assertDispatched(SyncUserFromPelicanWebhookJob::class, fn ($job) => $job->pelicanUserId === 7);
    }

    public function test_skips_user_sync_in_shop_stripe_mode(): void
    {
        // In shop_stripe mode users come from Stripe / OAuth — Pelican must
        // not be allowed to create ghost users out of band.
        Setting::updateOrCreate(['key' => 'bridge_mode'], ['value' => BridgeMode::ShopStripe->value]);
        app(SettingsService::class)->clearCache();

        Bus::fake();

        $response = $this->pelicanPost([
            'event' => 'eloquent.created: App\\Models\\User',
            'data' => ['id' => 7, 'email' => 'pelican-user@example.com', 'updated_at' => '2026-04-22 10:00:00'],
        ]);

        $response->assertStatus(200);
        Bus::assertNotDispatched(SyncUserFromPelicanWebhookJob::class);
        $this->assertDatabaseHas('pelican_processed_events', [
            'event_type' => 'eloquent.created: App\\Models\\User',
            'pelican_model_id' => 7,
        ]);
    }

    public function test_dedupes_repeated_event_via_idempotency_hash(): void
    {
        Bus::fake();

        $payload = $this->serverCreatedPayload(pelicanServerId: 99);

        $first = $this->pelicanPost($payload);
        $first->assertStatus(200);

        $second = $this->pelicanPost($payload);
        $second->assertStatus(200);
        $second->assertJson(['idempotent' => true]);

        Bus::assertDispatchedTimes(SyncServerFromPelicanWebhookJob::class, 1);
        $this->assertDatabaseCount('pelican_processed_events', 1);
    }

    public function test_returns_200_for_unknown_event_type_without_dispatch(): void
    {
        Bus::fake();

        $response = $this->pelicanPost([
            'event' => 'eloquent.updated: App\\Models\\Setting',
            'data' => ['id' => 5, 'updated_at' => '2026-04-22 10:00:00'],
        ]);

        $response->assertStatus(200);
        Bus::assertNothingDispatched();
        $this->assertDatabaseHas('pelican_processed_events', [
            'event_type' => 'eloquent.updated: App\\Models\\Setting',
            'pelican_model_id' => 5,
        ]);
    }

    public function test_records_processed_event_with_payload_summary(): void
    {
        Bus::fake();

        $this->pelicanPost($this->serverCreatedPayload(pelicanServerId: 77));

        $row = \App\Models\PelicanProcessedEvent::where('pelican_model_id', 77)->firstOrFail();
        $this->assertSame('SyncServerFromPelicanWebhookJob', $row->payload_summary['dispatched']);
        $this->assertSame(77, $row->payload_summary['pelican_server_id']);
    }

    public function test_returns_200_and_dispatches_reconcile_when_server_event_payload_is_broken(): void
    {
        // Pelican has a known bug shipping `(array) $model` instead of
        // `$model->toArray()` for Eloquent CRUD events — we lose the model
        // id but still know the event class. Fallback : trigger a full
        // mirror reconciliation against Pelican's canonical server list.
        Bus::fake();
        Cache::flush();

        $response = $this->pelicanPost([
            'event' => 'eloquent.created: App\\Models\\Server',
            'data' => [],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['skipped' => 'missing_fields', 'fallback' => 'reconcile_dispatched']);
        Bus::assertDispatched(ReconcilePelicanMirrorJob::class, 1);
        Bus::assertNotDispatched(SyncServerFromPelicanWebhookJob::class);
    }

    public function test_broken_non_server_event_does_not_trigger_reconcile(): void
    {
        // Reconciliation only knows about servers — broken payloads on
        // User / Node / Egg events just log and bail out.
        Bus::fake();
        Cache::flush();

        $response = $this->pelicanPost([
            'event' => 'eloquent.created: App\\Models\\User',
            'data' => [],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['skipped' => 'missing_fields']);
        Bus::assertNotDispatched(ReconcilePelicanMirrorJob::class);
    }

    public function test_burst_of_broken_server_events_dispatches_reconcile_only_once(): void
    {
        // Pelican fires 3-4 events for a single server lifecycle action
        // (created:Server, updated:Allocation, updated:Server, …) — they
        // arrive within seconds. The debounce lock keeps the queue clean.
        Bus::fake();
        Cache::flush();

        $first = $this->pelicanPost([
            'event' => 'eloquent.created: App\\Models\\Server',
            'data' => [],
        ]);
        $second = $this->pelicanPost([
            'event' => 'eloquent.updated: App\\Models\\Server',
            'data' => [],
        ]);
        $third = $this->pelicanPost([
            'event' => 'eloquent.deleted: App\\Models\\Server',
            'data' => [],
        ]);

        $first->assertJson(['fallback' => 'reconcile_dispatched']);
        $second->assertJson(['fallback' => 'reconcile_debounced']);
        $third->assertJson(['fallback' => 'reconcile_debounced']);

        Bus::assertDispatchedTimes(ReconcilePelicanMirrorJob::class, 1);
    }

    public function test_short_form_event_from_x_webhook_event_header_is_recognised(): void
    {
        // What Pelican actually sends when the admin uses the {{event}}
        // template in the X-Webhook-Event header AND the model is shipped
        // under `payload` (not `data`). Cover the exact UI defaults.
        Bus::fake();

        $response = $this->postJson(
            '/api/pelican/webhook',
            [
                'payload' => [
                    'id' => 555,
                    'identifier' => 'shortf',
                    'name' => 'short-form-server',
                    'user' => 1,
                    'updated_at' => '2026-04-22 10:00:00',
                ],
            ],
            [
                'Authorization' => 'Bearer '.self::TOKEN,
                'X-Webhook-Event' => 'created: Server',
            ],
        );

        $response->assertStatus(200);
        Bus::assertDispatched(SyncServerFromPelicanWebhookJob::class, fn ($job) => $job->pelicanServerId === 555);
        $this->assertDatabaseHas('pelican_processed_events', [
            'event_type' => 'created: Server',
            'pelican_model_id' => 555,
        ]);
    }

    public function test_short_form_deleted_event_dispatches_sync_job(): void
    {
        Bus::fake();

        $response = $this->postJson(
            '/api/pelican/webhook',
            [
                'payload' => ['id' => 777, 'updated_at' => '2026-04-22 10:00:00'],
            ],
            [
                'Authorization' => 'Bearer '.self::TOKEN,
                'X-Webhook-Event' => 'deleted: Server',
            ],
        );

        $response->assertStatus(200);
        Bus::assertDispatched(SyncServerFromPelicanWebhookJob::class, function ($job): bool {
            return $job->pelicanServerId === 777
                && str_contains($job->eventType, 'deleted');
        });
    }

    public function test_short_form_user_created_event_dispatches_user_sync(): void
    {
        Bus::fake();

        $response = $this->postJson(
            '/api/pelican/webhook',
            [
                'payload' => ['id' => 11, 'email' => 'u@x.com', 'updated_at' => '2026-04-22 10:00:00'],
            ],
            [
                'Authorization' => 'Bearer '.self::TOKEN,
                'X-Webhook-Event' => 'created: User',
            ],
        );

        $response->assertStatus(200);
        Bus::assertDispatched(SyncUserFromPelicanWebhookJob::class, fn ($job) => $job->pelicanUserId === 11);
    }

    public function test_server_installed_custom_event_dispatches_sync_job(): void
    {
        Bus::fake();

        $response = $this->postJson(
            '/api/pelican/webhook',
            [
                'payload' => ['id' => 33, 'name' => 'srv', 'identifier' => 'iden', 'user' => 1, 'updated_at' => '2026-04-22 10:00:00'],
            ],
            [
                'Authorization' => 'Bearer '.self::TOKEN,
                'X-Webhook-Event' => 'event: Server\\Installed',
            ],
        );

        $response->assertStatus(200);
        Bus::assertDispatched(SyncServerFromPelicanWebhookJob::class, fn ($job) => $job->pelicanServerId === 33);
    }

    public function test_server_installed_event_with_nested_server_payload_dispatches_sync_job(): void
    {
        // Defensive fallback : if Pelican ever switches to named-key payloads
        // for custom multi-arg events, this `server` shape should still work.
        // The actual current Pelican shape is exercised by the
        // *_with_numeric_keys test below.
        Bus::fake();

        $response = $this->postJson(
            '/api/pelican/webhook',
            [
                'server' => ['id' => 266, 'name' => 'srv', 'identifier' => 'iden', 'updated_at' => '2026-04-29 02:51:24'],
                'successful' => true,
                'initialInstall' => false,
                'event' => 'event: Server\\Installed',
            ],
            [
                'Authorization' => 'Bearer '.self::TOKEN,
                'X-Webhook-Event' => 'App\\Events\\Server\\Installed',
            ],
        );

        $response->assertStatus(200);
        Bus::assertDispatched(SyncServerFromPelicanWebhookJob::class, fn ($job) => $job->pelicanServerId === 266);
    }

    public function test_server_installed_event_with_real_pelican_numeric_keys_dispatches_sync_job(): void
    {
        // The real Pelican shape for App\Events\Server\Installed.
        // ProcessWebhook in Pelican does not introspect constructor parameter
        // names — it ships `$this->data` as-is. For an event with
        // (Server $server, bool $successful, bool $initialInstall) the body
        // has the model under numeric key "0" and the booleans under "1"/"2".
        Bus::fake();

        $response = $this->postJson(
            '/api/pelican/webhook',
            [
                '0' => ['id' => 266, 'name' => 'srv', 'identifier' => 'iden', 'updated_at' => '2026-04-29 02:51:24'],
                '1' => true,
                '2' => false,
                'event' => 'event: Server\\Installed',
            ],
            [
                'Authorization' => 'Bearer '.self::TOKEN,
            ],
        );

        $response->assertStatus(200);
        Bus::assertDispatched(SyncServerFromPelicanWebhookJob::class, fn ($job) => $job->pelicanServerId === 266);
    }

    public function test_legacy_bridge_token_setting_still_works_as_fallback(): void
    {
        // Installs that haven't run the extract migration yet have only the
        // legacy `bridge_pelican_webhook_token`. The middleware falls back to
        // it when the new key is missing.
        Setting::where('key', 'pelican_webhook_token')->delete();
        Setting::updateOrCreate(
            ['key' => 'bridge_pelican_webhook_token'],
            ['value' => Crypt::encryptString(self::TOKEN)],
        );
        app(SettingsService::class)->clearCache();

        Bus::fake();

        $response = $this->pelicanPost($this->serverCreatedPayload(pelicanServerId: 100));

        $response->assertStatus(200);
        Bus::assertDispatched(SyncServerFromPelicanWebhookJob::class);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function pelicanPost(array $payload, ?string $token = null): \Illuminate\Testing\TestResponse
    {
        $token ??= self::TOKEN;

        return $this->postJson('/api/pelican/webhook', $payload, [
            'Authorization' => "Bearer {$token}",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serverCreatedPayload(int $pelicanServerId = 42, ?string $externalId = null): array
    {
        return [
            'event' => 'eloquent.created: App\\Models\\Server',
            'data' => [
                'id' => $pelicanServerId,
                'identifier' => 'abc12345',
                'name' => 'test-server',
                'status' => null,
                'suspended' => false,
                'user' => 1,
                'node_id' => 1,
                'egg_id' => 1,
                'external_id' => $externalId,
                'updated_at' => '2026-04-22 10:00:00',
            ],
        ];
    }
}
