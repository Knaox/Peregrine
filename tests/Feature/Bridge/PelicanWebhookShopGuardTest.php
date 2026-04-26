<?php

namespace Tests\Feature\Bridge;

use App\Enums\BridgeMode;
use App\Events\Bridge\ServerInstalled;
use App\Jobs\Bridge\SyncServerFromPelicanWebhookJob;
use App\Models\Server;
use App\Models\Setting;
use App\Models\User;
use App\Services\Pelican\DTOs\PelicanServer;
use App\Services\Pelican\DTOs\ServerLimits;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

/**
 * Locks the "Shop owns it" guard in SyncServerFromPelicanWebhookJob :
 *
 *  - When a local Server has a stripe_subscription_id OR plan_id, Pelican is
 *    only allowed to update install-related fields (status transition from
 *    `provisioning`, identifier, paymenter_service_id, egg_id). It must NEVER
 *    overwrite user_id, name, billing-status (`suspended`/`terminated`),
 *    plan_id, stripe_subscription_id.
 *
 *  - On the `provisioning` → `active` install transition AND shop_stripe
 *    mode, the job fires `ServerInstalled` so the "your server is playable"
 *    email goes out. Strict order : status update FIRST, event SECOND.
 *
 *  - On the same transition in Paymenter mode, the event is NOT fired
 *    (Paymenter sends its own emails — no double-email).
 *
 *  - Pelican-side deletion of a Shop-owned server in shop_stripe mode is
 *    treated as drift and the local row is preserved for admin review.
 *
 *  - In shop_stripe mode, a webhook for an unknown Pelican server is
 *    silently skipped (the local row is created by the Stripe flow, not by
 *    Pelican).
 */
class PelicanWebhookShopGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::updateOrCreate(['key' => 'bridge_mode'], ['value' => BridgeMode::ShopStripe->value]);
        Setting::updateOrCreate(['key' => 'pelican_webhook_enabled'], ['value' => 'true']);
        app(SettingsService::class)->clearCache();
    }

    private function mockPelicanGetServer(
        int $pelicanServerId,
        int $userId = 1,
        bool $isSuspended = false,
        string $name = 'remote-name',
        string $identifier = 'remote-id',
        ?string $status = null,
    ): void {
        $dto = new PelicanServer(
            id: $pelicanServerId,
            identifier: $identifier,
            name: $name,
            description: '',
            userId: $userId,
            nodeId: 1,
            eggId: 1,
            nestId: 0,
            isSuspended: $isSuspended,
            limits: new ServerLimits(memory: 1024, swap: 0, disk: 5000, io: 500, cpu: 100),
            status: $status,
        );

        $mock = Mockery::mock(PelicanApplicationService::class);
        $mock->shouldReceive('getServer')->andReturn($dto);
        $this->app->instance(PelicanApplicationService::class, $mock);
    }

    public function test_shop_owned_server_keeps_user_id_and_name(): void
    {
        $shopOwner = User::factory()->create();
        $server = Server::create([
            'pelican_server_id' => 50,
            'user_id' => $shopOwner->id,

            'stripe_subscription_id' => 'sub_shop_owned',
            'name' => 'shop-given-name',
            'identifier' => 'old-id',
            'status' => 'provisioning',
        ]);

        // Pelican reports a different name + a different owner — must be ignored.
        $this->mockPelicanGetServer(50, userId: 999, name: 'pelican-renamed', identifier: 'new-id');

        $job = new SyncServerFromPelicanWebhookJob(
            eventType: 'eloquent.updated: App\\Models\\Server',
            pelicanServerId: 50,
            payloadSnapshot: ['id' => 50, 'updated_at' => '2026-04-22 10:00:00'],
        );
        $job->handle(app(PelicanApplicationService::class), app(\App\Services\Bridge\BridgeModeService::class));

        $server->refresh();
        $this->assertSame($shopOwner->id, $server->user_id, 'Pelican must not reassign Shop-owned ownership');
        $this->assertSame('shop-given-name', $server->name, 'Pelican must not rename a Shop-owned server');
        $this->assertSame('sub_shop_owned', $server->stripe_subscription_id);
        $this->assertSame('new-id', $server->identifier, 'identifier mirroring is allowed (Pelican-only field)');
    }

    public function test_shop_owned_server_transitions_provisioning_to_active_and_fires_event(): void
    {
        Event::fake([ServerInstalled::class]);

        $shopOwner = User::factory()->create(['pelican_user_id' => 1]);
        $server = Server::create([
            'pelican_server_id' => 60,
            'user_id' => $shopOwner->id,

            'stripe_subscription_id' => 'sub_install_done',
            'name' => 'srv',
            'identifier' => 'iden',
            'status' => 'provisioning',
        ]);

        $this->mockPelicanGetServer(60, status: null); // null = install finished

        $job = new SyncServerFromPelicanWebhookJob(
            eventType: 'eloquent.updated: App\\Models\\Server',
            pelicanServerId: 60,
            payloadSnapshot: ['id' => 60, 'status' => null, 'updated_at' => '2026-04-22 10:00:00'],
        );
        $job->handle(app(PelicanApplicationService::class), app(\App\Services\Bridge\BridgeModeService::class));

        $server->refresh();
        $this->assertSame('active', $server->status);
        Event::assertDispatched(ServerInstalled::class, fn ($e) => $e->server->id === $server->id);
    }

    public function test_shop_owned_server_transitions_provisioning_to_failed_on_install_failed(): void
    {
        Event::fake([ServerInstalled::class]);

        $shopOwner = User::factory()->create();
        $server = Server::create([
            'pelican_server_id' => 61,
            'user_id' => $shopOwner->id,

            'stripe_subscription_id' => 'sub_install_failed',
            'name' => 'srv',
            'identifier' => 'iden',
            'status' => 'provisioning',
        ]);

        $this->mockPelicanGetServer(61, status: 'install_failed');

        $job = new SyncServerFromPelicanWebhookJob(
            eventType: 'eloquent.updated: App\\Models\\Server',
            pelicanServerId: 61,
            payloadSnapshot: ['id' => 61, 'status' => 'install_failed', 'updated_at' => '2026-04-22 10:00:00'],
        );
        $job->handle(app(PelicanApplicationService::class), app(\App\Services\Bridge\BridgeModeService::class));

        $server->refresh();
        $this->assertSame('provisioning_failed', $server->status);
        Event::assertNotDispatched(ServerInstalled::class);
    }

    public function test_shop_owned_active_server_is_not_demoted_to_suspended_via_pelican(): void
    {
        // Billing status (suspended/terminated) belongs to Stripe webhooks.
        // Pelican reporting suspended must not flip a Shop-owned active row.
        $shopOwner = User::factory()->create();
        $server = Server::create([
            'pelican_server_id' => 70,
            'user_id' => $shopOwner->id,

            'stripe_subscription_id' => 'sub_active',
            'name' => 'srv',
            'identifier' => 'iden',
            'status' => 'active',
        ]);

        $this->mockPelicanGetServer(70, isSuspended: true);

        $job = new SyncServerFromPelicanWebhookJob(
            eventType: 'eloquent.updated: App\\Models\\Server',
            pelicanServerId: 70,
            payloadSnapshot: ['id' => 70, 'status' => 'suspended', 'suspended' => true, 'updated_at' => '2026-04-22 10:00:00'],
        );
        $job->handle(app(PelicanApplicationService::class), app(\App\Services\Bridge\BridgeModeService::class));

        $server->refresh();
        $this->assertSame('active', $server->status, 'Shop billing status must not be overwritten by Pelican');
    }

    public function test_paymenter_mode_install_transition_does_not_fire_server_installed_event(): void
    {
        // Paymenter sends its own customer emails — Peregrine must not
        // double-email even when the install transition happens via webhook.
        Event::fake([ServerInstalled::class]);
        Setting::updateOrCreate(['key' => 'bridge_mode'], ['value' => BridgeMode::Paymenter->value]);
        app(SettingsService::class)->clearCache();

        $owner = User::factory()->create(['pelican_user_id' => 1]);
        $server = Server::create([
            'pelican_server_id' => 80,
            'user_id' => $owner->id,
            // No plan_id, no stripe_subscription_id → Paymenter / admin-imported.
            'name' => 'srv',
            'identifier' => 'iden',
            'status' => 'provisioning',
        ]);

        $this->mockPelicanGetServer(80, userId: 1, status: null);

        $job = new SyncServerFromPelicanWebhookJob(
            eventType: 'eloquent.updated: App\\Models\\Server',
            pelicanServerId: 80,
            payloadSnapshot: ['id' => 80, 'user' => 1, 'status' => null, 'updated_at' => '2026-04-22 10:00:00'],
        );
        $job->handle(app(PelicanApplicationService::class), app(\App\Services\Bridge\BridgeModeService::class));

        Event::assertNotDispatched(ServerInstalled::class);
    }

    public function test_pelican_deletion_of_shop_owned_server_preserves_local_row(): void
    {
        $shopOwner = User::factory()->create();
        Server::create([
            'pelican_server_id' => 90,
            'user_id' => $shopOwner->id,

            'stripe_subscription_id' => 'sub_will_drift',
            'name' => 'srv',
            'identifier' => 'iden',
            'status' => 'active',
        ]);

        // Deletion event short-circuits before any API call.
        $mock = Mockery::mock(PelicanApplicationService::class);
        $this->app->instance(PelicanApplicationService::class, $mock);

        $job = new SyncServerFromPelicanWebhookJob(
            eventType: 'eloquent.deleted: App\\Models\\Server',
            pelicanServerId: 90,
            payloadSnapshot: ['id' => 90],
        );
        $job->handle($mock, app(\App\Services\Bridge\BridgeModeService::class));

        $this->assertDatabaseHas('servers', ['pelican_server_id' => 90]);
    }

    public function test_unknown_server_in_shop_stripe_mode_is_silently_skipped(): void
    {
        // Local row is supposed to be created by ProvisionServerJob (via
        // Stripe webhook). If a Pelican webhook arrives first, ignore.
        $this->mockPelicanGetServer(123);

        $job = new SyncServerFromPelicanWebhookJob(
            eventType: 'eloquent.created: App\\Models\\Server',
            pelicanServerId: 123,
            payloadSnapshot: ['id' => 123, 'user' => 1, 'updated_at' => '2026-04-22 10:00:00'],
        );
        $job->handle(app(PelicanApplicationService::class), app(\App\Services\Bridge\BridgeModeService::class));

        $this->assertDatabaseMissing('servers', ['pelican_server_id' => 123]);
    }
}
