<?php

namespace Tests\Feature;

use App\Jobs\SubscriptionUpdateJob;
use App\Jobs\SuspendScheduledServersJob;
use App\Jobs\SuspendServerJob;
use App\Models\Server;
use App\Models\ServerConfiguration;
use App\Models\Setting;
use App\Models\User;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Two-phase cancellation lifecycle (auto-renew disabled) :
 *  - cancel_at_period_end → schedule suspension at period end AND deletion at
 *    period end + grace, while the server stays active.
 *  - re-enabling renewal clears both scheduled dates.
 *  - SuspendScheduledServersJob suspends due servers and keeps the deletion date.
 *  - SuspendServerJob (subscription.deleted) does not overwrite a pre-planned
 *    deletion date.
 */
class CancellationSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabling_auto_renew_schedules_suspension_then_deletion(): void
    {
        Setting::updateOrCreate(['key' => 'bridge_grace_period_days'], ['value' => '7']);
        app(SettingsService::class)->clearCache();

        $server = $this->makeServer('sub_autorenew_off');
        $cancelAt = now()->addDays(10)->startOfSecond();

        $pelicanMock = Mockery::mock(PelicanApplicationService::class);
        (new SubscriptionUpdateJob(
            eventId: 'evt_ar_off',
            stripeSubscriptionId: 'sub_autorenew_off',
            newConfigurationId: null,
            newStatus: 'active',
            cancelAtPeriodEnd: true,
            cancelAt: $cancelAt->getTimestamp(),
        ))->handle($pelicanMock, app(SettingsService::class));

        $server->refresh();
        // Server stays usable until the paid period ends.
        $this->assertSame('active', $server->status);
        $this->assertNotNull($server->scheduled_suspension_at);
        $this->assertSame($cancelAt->getTimestamp(), $server->scheduled_suspension_at->getTimestamp());
        $this->assertNotNull($server->scheduled_deletion_at);
        $this->assertSame(
            $cancelAt->copy()->addDays(7)->getTimestamp(),
            $server->scheduled_deletion_at->getTimestamp(),
        );
    }

    public function test_reenabling_auto_renew_clears_scheduled_dates(): void
    {
        $server = $this->makeServer('sub_autorenew_on');
        $server->update([
            'scheduled_suspension_at' => now()->addDays(5),
            'scheduled_deletion_at' => now()->addDays(19),
        ]);

        $pelicanMock = Mockery::mock(PelicanApplicationService::class);
        (new SubscriptionUpdateJob(
            eventId: 'evt_ar_on',
            stripeSubscriptionId: 'sub_autorenew_on',
            newConfigurationId: null,
            newStatus: 'active',
            cancelAtPeriodEnd: false,
            cancelAt: null,
        ))->handle($pelicanMock, app(SettingsService::class));

        $server->refresh();
        $this->assertNull($server->scheduled_suspension_at);
        $this->assertNull($server->scheduled_deletion_at);
    }

    public function test_suspend_scheduled_servers_job_suspends_due_servers_keeping_deletion_date(): void
    {
        $due = $this->makeServer('sub_due');
        $deletionAt = now()->addDays(7)->startOfSecond();
        $due->update([
            'scheduled_suspension_at' => now()->subMinute(),
            'scheduled_deletion_at' => $deletionAt,
        ]);

        $notDue = $this->makeServer('sub_not_due');
        $notDue->update([
            'scheduled_suspension_at' => now()->addDays(3),
            'scheduled_deletion_at' => now()->addDays(17),
        ]);

        $pelicanMock = Mockery::mock(PelicanApplicationService::class);
        $pelicanMock->shouldReceive('suspendServer')->once()->with($due->pelican_server_id);
        $this->app->instance(PelicanApplicationService::class, $pelicanMock);

        (new SuspendScheduledServersJob())->handle($pelicanMock);

        $due->refresh();
        $this->assertSame('suspended', $due->status);
        $this->assertNull($due->scheduled_suspension_at);
        $this->assertSame($deletionAt->getTimestamp(), $due->scheduled_deletion_at->getTimestamp());

        $notDue->refresh();
        $this->assertSame('active', $notDue->status);
        $this->assertNotNull($notDue->scheduled_suspension_at);
    }

    public function test_suspend_server_job_does_not_overwrite_preplanned_deletion_date(): void
    {
        Setting::updateOrCreate(['key' => 'bridge_grace_period_days'], ['value' => '14']);
        app(SettingsService::class)->clearCache();

        $server = $this->makeServer('sub_preplanned');
        $preplanned = now()->addDays(3)->startOfSecond();
        $server->update(['scheduled_deletion_at' => $preplanned]);

        $pelicanMock = Mockery::mock(PelicanApplicationService::class);
        $pelicanMock->shouldReceive('suspendServer')->once();
        $this->app->instance(PelicanApplicationService::class, $pelicanMock);

        (new SuspendServerJob('evt_pre', 'sub_preplanned', scheduleDeletion: true))
            ->handle($pelicanMock, app(SettingsService::class));

        $server->refresh();
        $this->assertSame('suspended', $server->status);
        // The pre-planned exact date must survive (not replaced by now()+grace).
        $this->assertSame($preplanned->getTimestamp(), $server->scheduled_deletion_at->getTimestamp());
    }

    private function makeServer(string $subscriptionId): Server
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
        $configuration = ServerConfiguration::create([
            'internal_name' => 'cfg-'.Str::random(6),
            'name_template' => '{user.username}-{configuration.internal_name}',
            'egg_id' => $egg->id, 'nest_id' => $nest->id, 'node_id' => $node->id,
            'ram' => 1024, 'cpu' => 100, 'disk' => 5000,
        ]);

        return Server::create([
            'user_id' => User::factory()->create()->id,
            'pelican_server_id' => mt_rand(100, 999),
            'name' => 'srv-test',
            'status' => 'active',
            'egg_id' => $egg->id,
            'server_configuration_id' => $configuration->id,
            'stripe_subscription_id' => $subscriptionId,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
