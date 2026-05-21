<?php

declare(strict_types=1);

namespace Tests\Feature\Bridge;

use App\Jobs\Bridge\SyncServerFromPelicanWebhookJob;
use App\Models\Server;
use App\Models\ServerConfiguration;
use App\Models\Setting;
use App\Models\User;
use App\Services\Bridge\PelicanMirrorReconciler;
use App\Services\Integrations\IntegrationStatusService;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\SettingsService;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Locks the recentred ownership criterion : Pelican may create / delete any
 * server that has NO Stripe subscription, while servers carrying a
 * stripe_subscription_id stay lifecycle-owned by Stripe (Pelican deletions
 * are treated as drift). Replaces the old, broader "has a plan id" guard so
 * that hand-managed servers in Pelican stay in sync.
 */
class PelicanServerSubscriptionGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Shop custom mode : Stripe is wired.
        Setting::updateOrCreate(['key' => 'bridge_stripe_webhook_secret'], ['value' => 'whsec_test_seed']);
        Setting::updateOrCreate(['key' => 'pelican_webhook_enabled'], ['value' => 'true']);
        app(SettingsService::class)->clearCache();
    }

    private function serverConfiguration(): ServerConfiguration
    {
        return ServerConfiguration::create([
            'internal_name' => 'cfg-no-sub',
            'name_template' => '{user.username}-{configuration.internal_name}',
            'ram' => 1024,
            'cpu' => 100,
            'disk' => 5000,
        ]);
    }

    public function test_deletion_removes_local_server_without_subscription(): void
    {
        $owner = User::factory()->create();
        Server::create([
            'pelican_server_id' => 200,
            'user_id' => $owner->id,
            // Plan attached but NO stripe subscription → Pelican owns its lifecycle.
            'server_configuration_id' => $this->serverConfiguration()->id,
            'name' => 'free-server',
            'status' => 'active',
            'idempotency_key' => 'free-200',
        ]);

        $mock = Mockery::mock(PelicanApplicationService::class);
        $this->app->instance(PelicanApplicationService::class, $mock);

        $job = new SyncServerFromPelicanWebhookJob(
            eventType: 'eloquent.deleted: App\\Models\\Server',
            pelicanServerId: 200,
            payloadSnapshot: ['id' => 200],
        );
        $job->handle($mock, app(IntegrationStatusService::class));

        $this->assertDatabaseMissing('servers', ['pelican_server_id' => 200]);
    }

    public function test_deletion_preserves_local_server_with_subscription(): void
    {
        $owner = User::factory()->create();
        Server::create([
            'pelican_server_id' => 201,
            'user_id' => $owner->id,
            'stripe_subscription_id' => 'sub_live',
            'name' => 'paid-server',
            'status' => 'active',
            'idempotency_key' => 'paid-201',
        ]);

        $mock = Mockery::mock(PelicanApplicationService::class);
        $this->app->instance(PelicanApplicationService::class, $mock);

        $job = new SyncServerFromPelicanWebhookJob(
            eventType: 'eloquent.deleted: App\\Models\\Server',
            pelicanServerId: 201,
            payloadSnapshot: ['id' => 201],
        );
        $job->handle($mock, app(IntegrationStatusService::class));

        $this->assertDatabaseHas('servers', ['pelican_server_id' => 201]);
    }

    public function test_unknown_server_is_released_on_early_attempt_under_stripe(): void
    {
        // Provisioning race : the local row may still be landing. We release
        // rather than create a duplicate, until the last attempt.
        $this->mockGetServerThrows();

        $mockJob = Mockery::mock(QueueJob::class);
        $mockJob->shouldReceive('attempts')->andReturn(1);
        $mockJob->shouldReceive('release')->once();
        $mockJob->shouldReceive('hasFailed')->andReturn(false);

        $job = new SyncServerFromPelicanWebhookJob(
            eventType: 'eloquent.created: App\\Models\\Server',
            pelicanServerId: 300,
            payloadSnapshot: ['id' => 300, 'user' => 1, 'updated_at' => '2026-05-21 10:00:00'],
        );
        $job->setJob($mockJob);
        $job->handle(app(PelicanApplicationService::class), app(IntegrationStatusService::class));

        $this->assertDatabaseMissing('servers', ['pelican_server_id' => 300]);
    }

    public function test_unknown_server_is_mirrored_as_hand_created_on_last_attempt(): void
    {
        // Genuinely created in the Pelican panel : no local row will ever
        // appear, so on the final attempt we mirror it (no subscription).
        User::factory()->create(['pelican_user_id' => 7]);
        $this->mockGetServerThrows();

        $mockJob = Mockery::mock(QueueJob::class);
        $mockJob->shouldReceive('attempts')->andReturn(3); // == $tries
        $mockJob->shouldReceive('hasFailed')->andReturn(false);

        $job = new SyncServerFromPelicanWebhookJob(
            eventType: 'eloquent.created: App\\Models\\Server',
            pelicanServerId: 301,
            payloadSnapshot: ['id' => 301, 'user' => 7, 'name' => 'hand-made', 'updated_at' => '2026-05-21 10:00:00'],
        );
        $job->setJob($mockJob);
        $job->handle(app(PelicanApplicationService::class), app(IntegrationStatusService::class));

        $this->assertDatabaseHas('servers', [
            'pelican_server_id' => 301,
            'name' => 'hand-made',
            'stripe_subscription_id' => null,
        ]);
    }

    public function test_reconciler_does_not_delete_orphan_with_subscription(): void
    {
        $owner = User::factory()->create();
        Server::create([
            'pelican_server_id' => 400,
            'user_id' => $owner->id,
            'stripe_subscription_id' => 'sub_orphan',
            'name' => 'paid-orphan',
            'status' => 'active',
            'idempotency_key' => 'paid-400',
        ]);

        // Pelican no longer lists this server → it is orphan locally.
        $mock = Mockery::mock(PelicanApplicationService::class);
        $mock->shouldReceive('listServers')->andReturn([]);

        (new PelicanMirrorReconciler($mock))->reconcile();

        $this->assertDatabaseHas('servers', ['pelican_server_id' => 400]);
    }

    public function test_reconciler_deletes_orphan_without_subscription(): void
    {
        $owner = User::factory()->create();
        Server::create([
            'pelican_server_id' => 401,
            'user_id' => $owner->id,
            'name' => 'free-orphan',
            'status' => 'active',
            'idempotency_key' => 'free-401',
        ]);

        $mock = Mockery::mock(PelicanApplicationService::class);
        $mock->shouldReceive('listServers')->andReturn([]);

        (new PelicanMirrorReconciler($mock))->reconcile();

        $this->assertDatabaseMissing('servers', ['pelican_server_id' => 401]);
    }

    private function mockGetServerThrows(): void
    {
        $mock = Mockery::mock(PelicanApplicationService::class);
        $mock->shouldReceive('getServer')->andThrow(new \RuntimeException('refetch failed'));
        $this->app->instance(PelicanApplicationService::class, $mock);
    }
}
