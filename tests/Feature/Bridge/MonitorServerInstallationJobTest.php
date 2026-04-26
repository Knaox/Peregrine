<?php

namespace Tests\Feature\Bridge;

use App\Events\Bridge\ServerInstalled;
use App\Jobs\Bridge\MonitorServerInstallationJob;
use App\Models\Server;
use App\Models\User;
use App\Services\Pelican\DTOs\PelicanServer;
use App\Services\Pelican\DTOs\ServerLimits;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

/**
 * Locks the polling-mode + short-circuit behaviour of MonitorServerInstallationJob.
 *
 *  - Short-circuit when status is already `active` / `provisioning_failed`
 *    (the webhook beat us — never double-fire ServerInstalled)
 *  - Fire ServerInstalled only when status was `provisioning` and we flip it
 *  - Short and long modes both work, with their respective backoff windows
 */
class MonitorServerInstallationJobTest extends TestCase
{
    use RefreshDatabase;

    private function mockPelican(?string $remoteStatus): void
    {
        $dto = new PelicanServer(
            id: 1,
            identifier: 'iden',
            name: 'srv',
            description: '',
            userId: 1,
            nodeId: 1,
            eggId: 1,
            nestId: 0,
            isSuspended: false,
            limits: new ServerLimits(memory: 1024, swap: 0, disk: 5000, io: 500, cpu: 100),
            status: $remoteStatus,
        );
        $mock = Mockery::mock(PelicanApplicationService::class);
        $mock->shouldReceive('getServer')->andReturn($dto);
        $this->app->instance(PelicanApplicationService::class, $mock);
    }

    private function makeServer(string $status): Server
    {
        $owner = User::factory()->create();

        return Server::create([
            'pelican_server_id' => 1,
            'user_id' => $owner->id,
            'name' => 'srv',
            'identifier' => 'iden',
            'status' => $status,
        ]);
    }

    public function test_short_circuits_when_status_already_active(): void
    {
        Event::fake([ServerInstalled::class]);
        $server = $this->makeServer('active');

        // Pelican mock should NEVER be called — short-circuit happens first.
        $mock = Mockery::mock(PelicanApplicationService::class);
        $mock->shouldNotReceive('getServer');
        $this->app->instance(PelicanApplicationService::class, $mock);

        (new MonitorServerInstallationJob($server->id))
            ->handle(app(PelicanApplicationService::class));

        Event::assertNotDispatched(ServerInstalled::class);
    }

    public function test_short_circuits_when_status_already_failed(): void
    {
        Event::fake([ServerInstalled::class]);
        $server = $this->makeServer('provisioning_failed');

        $mock = Mockery::mock(PelicanApplicationService::class);
        $mock->shouldNotReceive('getServer');
        $this->app->instance(PelicanApplicationService::class, $mock);

        (new MonitorServerInstallationJob($server->id))
            ->handle(app(PelicanApplicationService::class));

        Event::assertNotDispatched(ServerInstalled::class);
    }

    public function test_fires_server_installed_when_install_finishes_and_status_was_provisioning(): void
    {
        Event::fake([ServerInstalled::class]);
        $server = $this->makeServer('provisioning');
        $this->mockPelican(null); // null = install finished

        (new MonitorServerInstallationJob($server->id))
            ->handle(app(PelicanApplicationService::class));

        $server->refresh();
        $this->assertSame('active', $server->status);
        Event::assertDispatched(ServerInstalled::class);
    }

    public function test_marks_failed_when_pelican_reports_install_failed(): void
    {
        Event::fake([ServerInstalled::class]);
        $server = $this->makeServer('provisioning');
        $this->mockPelican('install_failed');

        (new MonitorServerInstallationJob($server->id))
            ->handle(app(PelicanApplicationService::class));

        $server->refresh();
        $this->assertSame('provisioning_failed', $server->status);
        Event::assertNotDispatched(ServerInstalled::class);
    }

    public function test_short_mode_reschedules_with_short_backoff(): void
    {
        Bus::fake();
        $server = $this->makeServer('provisioning');
        $this->mockPelican('installing'); // still installing → reschedule

        (new MonitorServerInstallationJob($server->id, MonitorServerInstallationJob::MODE_SHORT, 1))
            ->handle(app(PelicanApplicationService::class));

        Bus::assertDispatched(MonitorServerInstallationJob::class, function ($job) use ($server) {
            return $job->serverId === $server->id
                && $job->mode === MonitorServerInstallationJob::MODE_SHORT
                && $job->attemptNumber === 2;
        });
    }

    public function test_short_mode_gives_up_after_3_attempts(): void
    {
        Bus::fake();
        $server = $this->makeServer('provisioning');
        $this->mockPelican('installing');

        (new MonitorServerInstallationJob($server->id, MonitorServerInstallationJob::MODE_SHORT, 3))
            ->handle(app(PelicanApplicationService::class));

        Bus::assertNotDispatched(MonitorServerInstallationJob::class);
    }

    public function test_long_mode_keeps_rescheduling_well_past_short_cap(): void
    {
        Bus::fake();
        $server = $this->makeServer('provisioning');
        $this->mockPelican('installing');

        // Attempt 5 in long mode = still well within the 20-attempt cap.
        (new MonitorServerInstallationJob($server->id, MonitorServerInstallationJob::MODE_LONG, 5))
            ->handle(app(PelicanApplicationService::class));

        Bus::assertDispatched(MonitorServerInstallationJob::class, function ($job) {
            return $job->mode === MonitorServerInstallationJob::MODE_LONG
                && $job->attemptNumber === 6;
        });
    }
}
