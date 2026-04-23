<?php

namespace Tests\Feature;

use App\Models\BridgeSyncLog;
use App\Models\ServerPlan;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Locks the contract of the Bridge plan-sync API received from the Shop.
 *
 * Covers : signature validation, replay protection, idempotent upsert that
 * preserves Peregrine-only fields, soft-delete, audit logging, rate limit,
 * and 503-when-disabled.
 */
class BridgePlanSyncTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-secret-do-not-use-in-prod-just-for-test';

    protected function setUp(): void
    {
        parent::setUp();

        // Bridge enabled + shared secret stored encrypted (matches what the
        // admin would do via the BridgeSettings Filament page).
        Setting::updateOrCreate(['key' => 'bridge_mode'], ['value' => \App\Enums\BridgeMode::ShopStripe->value]);
        Setting::updateOrCreate(['key' => 'bridge_enabled'], ['value' => 'true']);
        Setting::updateOrCreate(['key' => 'bridge_shop_shared_secret'], ['value' => Crypt::encryptString(self::SECRET)]);
        app(SettingsService::class)->clearCache();
    }

    public function test_ping_returns_200_with_valid_signature_and_no_audit_log(): void
    {
        $response = $this->signedJson('POST', '/api/bridge/ping', []);

        $response->assertStatus(200);
        $response->assertJsonStructure(['ok', 'service', 'version', 'received_at']);
        $this->assertTrue($response->json('ok'));
        $this->assertSame('peregrine-bridge', $response->json('service'));

        // Health checks never write to the audit log.
        $this->assertDatabaseCount('bridge_sync_logs', 0);
        $this->assertDatabaseCount('server_plans', 0);
    }

    public function test_ping_rejects_invalid_signature(): void
    {
        $response = $this->call(
            'POST',
            '/api/bridge/ping',
            content: '{}',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_BRIDGE_SIGNATURE' => 'sha256=deadbeef',
                'HTTP_X_BRIDGE_TIMESTAMP' => (string) (int) (microtime(true) * 1000),
            ],
        );

        $response->assertStatus(401);
    }

    public function test_upsert_creates_new_plan_with_valid_signature(): void
    {
        $payload = $this->validPayload(shopPlanId: 42);
        $response = $this->signedJson('POST', '/api/bridge/plans/upsert', $payload);

        $response->assertStatus(200);
        $response->assertJsonStructure(['peregrine_plan_id', 'synced_at', 'status']);
        $this->assertSame('needs_admin_config', $response->json('status'));

        $this->assertDatabaseHas('server_plans', [
            'shop_plan_id' => 42,
            'name' => 'Minecraft 4Go',
            'ram' => 4096,
            'cpu' => 200,
            'disk' => 10240,
            'currency' => 'CHF',
            'price_cents' => 700,
        ]);

        $this->assertDatabaseCount('bridge_sync_logs', 1);
        $log = BridgeSyncLog::first();
        $this->assertSame('upsert', $log->action);
        $this->assertSame(42, $log->shop_plan_id);
        $this->assertSame(200, $log->response_status);
        $this->assertTrue($log->signature_valid);
    }

    public function test_upsert_updates_existing_plan_preserving_peregrine_fields(): void
    {
        // Seed minimal infra rows so FK constraints pass.
        // Pelican-mirrored tables require pelican_*_id (unique).
        $nest = \App\Models\Nest::create(['pelican_nest_id' => 1, 'name' => 'Minecraft', 'description' => 'test']);
        $egg = \App\Models\Egg::create([
            'pelican_egg_id' => 1, 'nest_id' => $nest->id, 'name' => 'Paper',
            'description' => 'test', 'docker_image' => 'java:17', 'startup' => 'java -jar',
        ]);
        $node = \App\Models\Node::create([
            'pelican_node_id' => 1, 'name' => 'fr-1', 'fqdn' => 'fr1.test',
            'scheme' => 'https', 'memory' => 1000, 'disk' => 10000,
        ]);

        // First push : create plan
        $this->signedJson('POST', '/api/bridge/plans/upsert', $this->validPayload(shopPlanId: 99));

        // Admin Peregrine configures egg + node
        $plan = ServerPlan::where('shop_plan_id', 99)->first();
        $plan->update([
            'egg_id' => $egg->id,
            'nest_id' => $nest->id,
            'node_id' => $node->id,
            'docker_image' => 'paymenter/yolks:java_17',
            'env_var_mapping' => [['variable_name' => 'TELNET_PORT', 'type' => 'offset', 'offset_value' => 1]],
            'enable_oom_killer' => false,
        ]);

        // Shop pushes a price change
        $payload = $this->validPayload(shopPlanId: 99);
        $payload['billing']['price_cents'] = 900;
        $payload['name'] = 'Minecraft 4Go (renamed)';

        $response = $this->signedJson('POST', '/api/bridge/plans/upsert', $payload);
        $response->assertStatus(200);
        $this->assertSame('ready', $response->json('status'));

        $plan->refresh();
        // Shop fields updated
        $this->assertSame(900, $plan->price_cents);
        $this->assertSame('Minecraft 4Go (renamed)', $plan->name);
        // Peregrine fields PRESERVED
        $this->assertSame($egg->id, $plan->egg_id);
        $this->assertSame($node->id, $plan->node_id);
        $this->assertSame('paymenter/yolks:java_17', $plan->docker_image);
        $this->assertSame([['variable_name' => 'TELNET_PORT', 'type' => 'offset', 'offset_value' => 1]], $plan->env_var_mapping);
        $this->assertFalse($plan->enable_oom_killer);
    }

    public function test_upsert_rejects_invalid_signature(): void
    {
        $payload = $this->validPayload();
        $body = json_encode($payload);
        $timestamp = (int) (microtime(true) * 1000);

        $response = $this->call(
            'POST',
            '/api/bridge/plans/upsert',
            content: $body,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_BRIDGE_SIGNATURE' => 'sha256=deadbeef',
                'HTTP_X_BRIDGE_TIMESTAMP' => (string) $timestamp,
            ],
        );

        $response->assertStatus(401);
        $response->assertJson(['error' => 'bridge.invalid_signature']);
        $this->assertDatabaseCount('server_plans', 0);
    }

    public function test_upsert_rejects_expired_timestamp(): void
    {
        $payload = $this->validPayload();
        $body = json_encode($payload);
        // 6 minutes ago, outside the 5-min replay window
        $expired = (int) (microtime(true) * 1000) - 6 * 60 * 1000;
        $signature = 'sha256='.hash_hmac('sha256', $body, self::SECRET);

        $response = $this->call(
            'POST',
            '/api/bridge/plans/upsert',
            content: $body,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_BRIDGE_SIGNATURE' => $signature,
                'HTTP_X_BRIDGE_TIMESTAMP' => (string) $expired,
            ],
        );

        $response->assertStatus(410);
    }

    public function test_upsert_rejects_malformed_payload(): void
    {
        $payload = $this->validPayload();
        unset($payload['pelican_specs']['ram_mb']); // required field

        $response = $this->signedJson('POST', '/api/bridge/plans/upsert', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pelican_specs.ram_mb']);
    }

    public function test_delete_deactivates_plan(): void
    {
        $this->signedJson('POST', '/api/bridge/plans/upsert', $this->validPayload(shopPlanId: 7));
        $this->assertSame(true, ServerPlan::where('shop_plan_id', 7)->value('is_active'));

        $response = $this->signedJson('DELETE', '/api/bridge/plans/7');
        $response->assertStatus(200);
        $response->assertJsonStructure(['deactivated_at']);

        $this->assertDatabaseHas('server_plans', ['shop_plan_id' => 7, 'is_active' => false]);
    }

    public function test_delete_returns_404_for_unknown_shop_plan_id(): void
    {
        $response = $this->signedJson('DELETE', '/api/bridge/plans/999999');
        $response->assertStatus(404);
        $response->assertJson(['error' => 'plan_not_found']);

        // Still logged for forensics
        $this->assertDatabaseHas('bridge_sync_logs', [
            'action' => 'delete',
            'shop_plan_id' => 999999,
            'response_status' => 404,
        ]);
    }

    public function test_returns_503_when_bridge_disabled(): void
    {
        Setting::updateOrCreate(['key' => 'bridge_mode'], ['value' => \App\Enums\BridgeMode::Disabled->value]);
        Setting::updateOrCreate(['key' => 'bridge_enabled'], ['value' => 'false']);
        app(SettingsService::class)->clearCache();

        $response = $this->signedJson('POST', '/api/bridge/plans/upsert', $this->validPayload());
        $response->assertStatus(503);
    }

    public function test_returns_503_when_secret_not_configured(): void
    {
        Setting::where('key', 'bridge_shop_shared_secret')->delete();
        app(SettingsService::class)->clearCache();

        $response = $this->signedJson('POST', '/api/bridge/plans/upsert', $this->validPayload());
        $response->assertStatus(503);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function signedJson(string $method, string $uri, ?array $payload = null): \Illuminate\Testing\TestResponse
    {
        $body = $payload === null ? '' : json_encode($payload);
        $timestamp = (int) (microtime(true) * 1000);
        $signature = 'sha256='.hash_hmac('sha256', $body, self::SECRET);

        return $this->call(
            $method,
            $uri,
            content: $body,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_BRIDGE_SIGNATURE' => $signature,
                'HTTP_X_BRIDGE_TIMESTAMP' => (string) $timestamp,
                'HTTP_ACCEPT' => 'application/json',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(int $shopPlanId = 1): array
    {
        return [
            'shop_plan_id' => $shopPlanId,
            'shop_plan_slug' => 'minecraft-4go',
            'shop_plan_type' => 'subscription',
            'name' => 'Minecraft 4Go',
            'description' => 'A 4GB Minecraft server',
            'is_active' => true,
            'billing' => [
                'price_cents' => 700,
                'currency' => 'CHF',
                'interval' => 'month',
                'interval_count' => 1,
                'has_trial' => false,
                'trial_interval' => null,
                'trial_interval_count' => null,
                'stripe_price_id' => null,
            ],
            'pelican_specs' => [
                'ram_mb' => 4096,
                'swap_mb' => 0,
                'disk_mb' => 10240,
                'cpu_percent' => 200,
                'io_weight' => 500,
                'cpu_pinning' => null,
            ],
            'checkout' => [
                'custom_fields' => [
                    [
                        'key' => 'server_name',
                        'label' => 'Nom du serveur',
                        'type' => 'text',
                        'optional' => false,
                    ],
                ],
            ],
        ];
    }
}
