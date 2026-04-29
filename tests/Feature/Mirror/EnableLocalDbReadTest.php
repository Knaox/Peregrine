<?php

namespace Tests\Feature\Mirror;

use App\Jobs\Mirror\EnableLocalDbReadJob;
use App\Models\MirrorBackfillProgress;
use App\Models\Node;
use App\Models\Pelican\Allocation;
use App\Models\Server;
use App\Models\User;
use App\Services\Mirror\AllocationMirrorBackfiller;
use App\Services\Mirror\BackupMirrorBackfiller;
use App\Services\Mirror\DatabaseMirrorBackfiller;
use App\Services\Mirror\MirrorBackfillOrchestrator;
use App\Services\Mirror\SubuserMirrorBackfiller;
use App\Services\Mirror\UserMirrorBackfiller;
use App\Services\Pelican\DTOs\PelicanAllocation;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

/**
 * End-to-end coverage of the "Activer la lecture DB locale" flow :
 *  - clicking the button dispatches the job and creates a progress row
 *  - the job runs the orchestrator and flips the flag on success
 *  - partial errors keep the flag OFF (safe-by-default)
 *  - disabling keeps mirror tables intact (re-activation is instant)
 *  - the allocation backfiller skips free ports end-to-end
 */
class EnableLocalDbReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_creates_running_progress_row_and_keeps_flag_off(): void
    {
        Bus::fake();

        $progress = MirrorBackfillProgress::startNew();
        EnableLocalDbReadJob::dispatch($progress->id);

        Bus::assertDispatched(
            EnableLocalDbReadJob::class,
            fn (EnableLocalDbReadJob $job) => $job->progressId === $progress->id,
        );
        $this->assertSame(MirrorBackfillProgress::STATE_RUNNING, $progress->fresh()->state);
        $this->assertSame('false', (string) app(SettingsService::class)->get('mirror_reads_enabled', 'false'));
    }

    public function test_job_flips_flag_on_zero_errors(): void
    {
        $progress = MirrorBackfillProgress::startNew();
        $orchestrator = $this->mockOrchestratorReporting([
            'users' => ['errors' => 0, 'written' => 0],
            'allocations' => ['errors' => 0, 'written' => 0],
            'databases' => ['errors' => 0, 'written' => 0],
            'backups' => ['errors' => 0, 'written' => 0],
            'subusers' => ['errors' => 0, 'written' => 0],
            '_total' => ['errors' => 0, 'duration_ms' => 42],
        ]);
        $this->app->instance(MirrorBackfillOrchestrator::class, $orchestrator);

        $job = new EnableLocalDbReadJob($progress->id);
        $job->handle($orchestrator, app(SettingsService::class));

        $this->assertSame('true', (string) app(SettingsService::class)->get('mirror_reads_enabled', 'false'));
        $this->assertSame(MirrorBackfillProgress::STATE_COMPLETED, $progress->fresh()->state);
        $this->assertNotNull($progress->fresh()->report);
    }

    public function test_job_keeps_flag_off_on_partial_failure(): void
    {
        $progress = MirrorBackfillProgress::startNew();
        $orchestrator = $this->mockOrchestratorReporting([
            'users' => ['errors' => 0, 'written' => 1],
            'allocations' => ['errors' => 1, 'written' => 0],
            '_total' => ['errors' => 1, 'duration_ms' => 100],
        ]);
        $this->app->instance(MirrorBackfillOrchestrator::class, $orchestrator);

        $job = new EnableLocalDbReadJob($progress->id);
        $job->handle($orchestrator, app(SettingsService::class));

        $this->assertSame('false', (string) app(SettingsService::class)->get('mirror_reads_enabled', 'false'));
        $this->assertSame(MirrorBackfillProgress::STATE_FAILED, $progress->fresh()->state);
        $this->assertStringContainsString('1 erreur', (string) $progress->fresh()->error);
    }

    public function test_disabling_keeps_mirror_rows_untouched(): void
    {
        $node = Node::create([
            'pelican_node_id' => 7,
            'name' => 'n7', 'fqdn' => 'n7', 'memory' => 1024, 'disk' => 1024,
        ]);
        $user = User::create(['name' => 'u', 'email' => 'u@x', 'password' => Hash::make('x')]);
        $server = Server::create([
            'user_id' => $user->id, 'pelican_server_id' => 70,
            'name' => 's70', 'identifier' => 'i70', 'status' => 'active',
        ]);
        Allocation::create([
            'pelican_allocation_id' => 1,
            'node_id' => $node->id, 'server_id' => $server->id,
            'ip' => '0.0.0.0', 'port' => 25565, 'is_locked' => false,
        ]);

        // Flip ON, then OFF.
        app(SettingsService::class)->set('mirror_reads_enabled', 'true');
        app(SettingsService::class)->set('mirror_reads_enabled', 'false');

        $this->assertSame('false', (string) app(SettingsService::class)->get('mirror_reads_enabled'));
        $this->assertDatabaseHas('pelican_allocations', ['pelican_allocation_id' => 1]);
    }

    public function test_orchestrator_skips_free_allocation_in_real_run(): void
    {
        $node = Node::create([
            'pelican_node_id' => 200,
            'name' => 'big', 'fqdn' => 'big', 'memory' => 4096, 'disk' => 8192,
        ]);
        $user = User::create(['name' => 'o', 'email' => 'o@x', 'password' => Hash::make('x')]);
        $server = Server::create([
            'user_id' => $user->id, 'pelican_server_id' => 2001,
            'name' => 's2001', 'identifier' => 'i2001', 'status' => 'active',
        ]);

        $pelican = Mockery::mock(PelicanApplicationService::class);
        $pelican->shouldReceive('listNodeAllocations')->andReturn([
            new PelicanAllocation(id: 1, ip: '0.0.0.0', ipAlias: null, port: 25500, notes: null, assigned: true,  serverId: 2001),
            new PelicanAllocation(id: 2, ip: '0.0.0.0', ipAlias: null, port: 25501, notes: null, assigned: false, serverId: null),
            new PelicanAllocation(id: 3, ip: '0.0.0.0', ipAlias: null, port: 25502, notes: null, assigned: false, serverId: null),
        ]);

        $report = (new AllocationMirrorBackfiller($pelican))->run();

        $this->assertSame(1, $report['written']);
        $this->assertSame(2, $report['skipped_unassigned']);
        $this->assertDatabaseCount('pelican_allocations', 1);
        $this->assertDatabaseHas('pelican_allocations', [
            'pelican_allocation_id' => 1,
            'server_id' => $server->id,
        ]);
    }

    /**
     * Build an orchestrator stub that returns a canned report. Bypasses
     * the real Pelican fetching so the test exercises only the job
     * lifecycle (progress row + flag flip + persistence).
     *
     * @param array<string, array<string, int>> $report
     */
    private function mockOrchestratorReporting(array $report): MirrorBackfillOrchestrator
    {
        $mock = Mockery::mock(MirrorBackfillOrchestrator::class, [
            app(UserMirrorBackfiller::class),
            app(AllocationMirrorBackfiller::class),
            app(DatabaseMirrorBackfiller::class),
            app(BackupMirrorBackfiller::class),
            app(SubuserMirrorBackfiller::class),
        ]);
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('run')->andReturn($report);

        return $mock;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
