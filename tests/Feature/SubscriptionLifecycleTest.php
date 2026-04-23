<?php

namespace Tests\Feature;

use App\Jobs\PurgeScheduledServerDeletionsJob;
use App\Jobs\SubscriptionUpdateJob;
use App\Jobs\SuspendServerJob;
use App\Models\Server;
use App\Models\ServerPlan;
use App\Models\Setting;
use App\Models\StripeProcessedEvent;
use App\Models\User;
use App\Notifications\Bridge\PaymentFailedNotification;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Sprint 2 lifecycle :
 *  - subscription.updated dispatches SubscriptionUpdateJob with right params
 *  - subscription.deleted dispatches SuspendServerJob with scheduleDeletion=true
 *  - SuspendServerJob actually sets scheduled_deletion_at = now() + grace_period
 *  - past_due path suspends but does NOT schedule deletion
 *  - invoice.payment_failed sends admin notification
 *  - PurgeScheduledServerDeletionsJob deletes only past-due, suspended servers
 *  - grace_period = 0 → schedule at now() (purged at next cron run)
 */
class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_local_only_ignore';

    protected function setUp(): void
    {
        parent::setUp();
        config(['bridge.stripe.webhook_secret' => self::WEBHOOK_SECRET]);
    }

    public function test_subscription_updated_dispatches_job_with_new_price_and_status(): void
    {
        Bus::fake();

        $event = $this->subscriptionUpdatedEvent(
            subscriptionId: 'sub_abc',
            newPriceId: 'price_new_123',
            newStatus: 'active',
        );

        $response = $this->signedStripePost($event);
        $response->assertStatus(200);

        Bus::assertDispatched(SubscriptionUpdateJob::class, function ($job) {
            return $job->stripeSubscriptionId === 'sub_abc'
                && $job->newStripePriceId === 'price_new_123'
                && $job->newStatus === 'active';
        });
    }

    public function test_subscription_deleted_dispatches_suspend_job_with_schedule_deletion(): void
    {
        Bus::fake();

        $event = [
            'id' => 'evt_'.Str::random(24),
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => ['id' => 'sub_xyz', 'status' => 'canceled']],
        ];

        $response = $this->signedStripePost($event);
        $response->assertStatus(200);

        Bus::assertDispatched(SuspendServerJob::class, function ($job) {
            return $job->stripeSubscriptionId === 'sub_xyz' && $job->scheduleDeletion === true;
        });
    }

    public function test_invoice_payment_failed_notifies_admins(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $nonAdmin = User::factory()->create(['is_admin' => false]);

        $event = [
            'id' => 'evt_'.Str::random(24),
            'type' => 'invoice.payment_failed',
            'data' => ['object' => [
                'id' => 'in_test_001',
                'subscription' => 'sub_test',
                'customer_email' => 'late@example.com',
                'amount_due' => 1500,
                'currency' => 'eur',
                'next_payment_attempt' => time() + 86400,
            ]],
        ];

        $this->signedStripePost($event)->assertStatus(200);

        Notification::assertSentTo($admin, PaymentFailedNotification::class);
        Notification::assertNotSentTo($nonAdmin, PaymentFailedNotification::class);
    }

    public function test_suspend_server_job_sets_scheduled_deletion_at_grace_period(): void
    {
        Setting::updateOrCreate(['key' => 'bridge_grace_period_days'], ['value' => '7']);
        app(SettingsService::class)->clearCache();

        [$server, ] = $this->makeServerWithSubscription('sub_grace');

        $pelicanMock = Mockery::mock(PelicanApplicationService::class);
        $pelicanMock->shouldReceive('suspendServer')->once()->with($server->pelican_server_id);
        $this->app->instance(PelicanApplicationService::class, $pelicanMock);

        (new SuspendServerJob('evt_test', 'sub_grace', scheduleDeletion: true))
            ->handle($pelicanMock, app(SettingsService::class));

        $server->refresh();
        $this->assertSame('suspended', $server->status);
        $this->assertNotNull($server->scheduled_deletion_at);
        $this->assertEquals(
            now()->addDays(7)->toDateString(),
            $server->scheduled_deletion_at->toDateString(),
        );
    }

    public function test_grace_period_zero_schedules_immediate_deletion(): void
    {
        Setting::updateOrCreate(['key' => 'bridge_grace_period_days'], ['value' => '0']);
        app(SettingsService::class)->clearCache();

        [$server, ] = $this->makeServerWithSubscription('sub_zero');

        $pelicanMock = Mockery::mock(PelicanApplicationService::class);
        $pelicanMock->shouldReceive('suspendServer')->once();
        $this->app->instance(PelicanApplicationService::class, $pelicanMock);

        (new SuspendServerJob('evt_zero', 'sub_zero', scheduleDeletion: true))
            ->handle($pelicanMock, app(SettingsService::class));

        $server->refresh();
        $this->assertNotNull($server->scheduled_deletion_at);
        // scheduled at now() with 1s tolerance
        $this->assertLessThanOrEqual(2, abs($server->scheduled_deletion_at->diffInSeconds(now())));
    }

    public function test_suspend_without_schedule_deletion_leaves_column_null(): void
    {
        [$server, ] = $this->makeServerWithSubscription('sub_no_delete');

        $pelicanMock = Mockery::mock(PelicanApplicationService::class);
        $pelicanMock->shouldReceive('suspendServer')->once();
        $this->app->instance(PelicanApplicationService::class, $pelicanMock);

        (new SuspendServerJob('evt', 'sub_no_delete', scheduleDeletion: false))
            ->handle($pelicanMock, app(SettingsService::class));

        $server->refresh();
        $this->assertSame('suspended', $server->status);
        $this->assertNull($server->scheduled_deletion_at);
    }

    public function test_purge_job_deletes_servers_past_grace_period_and_suspended(): void
    {
        [$pastDueAndSuspended, ] = $this->makeServerWithSubscription('sub_past');
        $pastDueAndSuspended->update([
            'status' => 'suspended',
            'scheduled_deletion_at' => now()->subDay(),
        ]);

        [$pastDueButActive, ] = $this->makeServerWithSubscription('sub_active');
        $pastDueButActive->update([
            'status' => 'active', // safety guard — should NOT be deleted
            'scheduled_deletion_at' => now()->subDay(),
        ]);

        [$futureScheduled, ] = $this->makeServerWithSubscription('sub_future');
        $futureScheduled->update([
            'status' => 'suspended',
            'scheduled_deletion_at' => now()->addDays(5),
        ]);

        $pelicanMock = Mockery::mock(PelicanApplicationService::class);
        $pelicanMock->shouldReceive('deleteServer')
            ->once()
            ->with($pastDueAndSuspended->pelican_server_id);
        $this->app->instance(PelicanApplicationService::class, $pelicanMock);

        (new PurgeScheduledServerDeletionsJob())->handle($pelicanMock);

        // Past-due + suspended → deleted
        $this->assertDatabaseMissing('servers', ['id' => $pastDueAndSuspended->id]);
        // Active (safety guard) → kept
        $this->assertDatabaseHas('servers', ['id' => $pastDueButActive->id]);
        // Future schedule → kept
        $this->assertDatabaseHas('servers', ['id' => $futureScheduled->id]);
    }

    private function signedStripePost(array $event): \Illuminate\Testing\TestResponse
    {
        $payload = json_encode($event);
        $ts = time();
        $sig = "t={$ts},v1=" . hash_hmac('sha256', "{$ts}.{$payload}", self::WEBHOOK_SECRET);

        return $this->call(
            'POST', '/api/stripe/webhook',
            content: $payload,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $sig,
                'HTTP_ACCEPT' => 'application/json',
            ],
        );
    }

    private function subscriptionUpdatedEvent(string $subscriptionId, string $newPriceId, string $newStatus): array
    {
        return [
            'id' => 'evt_'.Str::random(24),
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => $subscriptionId,
                    'object' => 'subscription',
                    'status' => $newStatus,
                    'items' => [
                        'data' => [[
                            'id' => 'si_test',
                            'price' => ['id' => $newPriceId],
                        ]],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{0: Server, 1: ServerPlan}
     */
    private function makeServerWithSubscription(string $subscriptionId): array
    {
        $nest = \App\Models\Nest::create(['pelican_nest_id' => mt_rand(1, 9999), 'name' => 'N']);
        $egg = \App\Models\Egg::create([
            'pelican_egg_id' => mt_rand(1, 9999), 'nest_id' => $nest->id,
            'name' => 'E', 'docker_image' => 't:1', 'startup' => 'echo',
        ]);
        $node = \App\Models\Node::create([
            'pelican_node_id' => mt_rand(1, 9999), 'name' => 'NN',
            'fqdn' => 'n.test', 'scheme' => 'https', 'memory' => 1, 'disk' => 1,
        ]);
        $plan = ServerPlan::create([
            'name' => 'P', 'stripe_price_id' => 'price_'.Str::random(8),
            'egg_id' => $egg->id, 'nest_id' => $nest->id, 'node_id' => $node->id,
            'ram' => 1024, 'cpu' => 100, 'disk' => 5000, 'is_active' => true,
        ]);
        $user = User::factory()->create();
        $server = Server::create([
            'user_id' => $user->id,
            'pelican_server_id' => mt_rand(100, 999),
            'name' => 'srv-test',
            'status' => 'active',
            'egg_id' => $egg->id,
            'plan_id' => $plan->id,
            'stripe_subscription_id' => $subscriptionId,
        ]);
        return [$server, $plan];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
