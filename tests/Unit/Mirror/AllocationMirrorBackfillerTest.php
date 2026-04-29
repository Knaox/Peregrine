<?php

namespace Tests\Unit\Mirror;

use App\Models\Node;
use App\Models\Pelican\Allocation;
use App\Models\Server;
use App\Models\User;
use App\Services\Mirror\AllocationMirrorBackfiller;
use App\Services\Pelican\DTOs\PelicanAllocation;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

/**
 * Pins the contract that fixed the original bug : the mirror table must
 * NOT contain free allocations. Each Pelican response holds a mix of
 * assigned + unassigned ports — only those bearing a server_id should
 * survive into `pelican_allocations`.
 */
class AllocationMirrorBackfillerTest extends TestCase
{
    use RefreshDatabase;

    public function test_skips_unassigned_allocations(): void
    {
        $node = $this->makeNode(101);
        $server = $this->makeServer(1001);

        $pelican = $this->mockPelicanWith([
            $this->alloc(1, $server->pelican_server_id),
            $this->alloc(2, null), // free port — must be skipped
            $this->alloc(3, null),
        ]);

        $report = (new AllocationMirrorBackfiller($pelican))->run();

        $this->assertSame(1, $report['written'], 'only the assigned allocation is written');
        $this->assertSame(2, $report['skipped_unassigned'], 'two free ports skipped');
        $this->assertDatabaseCount('pelican_allocations', 1);
        $this->assertDatabaseHas('pelican_allocations', [
            'pelican_allocation_id' => 1,
            'server_id' => $server->id,
            'node_id' => $node->id,
        ]);
    }

    public function test_skips_when_local_server_unknown(): void
    {
        $this->makeNode(101);
        // No local Server row matching pelican_server_id 9999

        $pelican = $this->mockPelicanWith([
            $this->alloc(1, 9999),
        ]);

        $report = (new AllocationMirrorBackfiller($pelican))->run();

        $this->assertSame(0, $report['written']);
        $this->assertSame(1, $report['skipped_unassigned']);
        $this->assertDatabaseCount('pelican_allocations', 0);
    }

    public function test_prunes_orphan_rows_no_longer_assigned(): void
    {
        $node = $this->makeNode(101);
        $server = $this->makeServer(1001);

        // Stale row from a previous run — Pelican no longer knows about it.
        Allocation::create([
            'pelican_allocation_id' => 99,
            'node_id' => $node->id,
            'server_id' => $server->id,
            'ip' => '0.0.0.0',
            'port' => 25500,
            'is_locked' => false,
        ]);

        $pelican = $this->mockPelicanWith([
            $this->alloc(1, $server->pelican_server_id),
        ]);

        $report = (new AllocationMirrorBackfiller($pelican))->run();

        $this->assertSame(1, $report['written']);
        $this->assertSame(1, $report['removed_orphans']);
        $this->assertDatabaseHas('pelican_allocations', ['pelican_allocation_id' => 1]);
        // Allocation uses SoftDeletes — pruned rows get deleted_at set,
        // matching the legacy webhook job's soft-delete behaviour.
        $this->assertSoftDeleted('pelican_allocations', ['pelican_allocation_id' => 99]);
    }

    public function test_records_node_fetch_error_without_aborting(): void
    {
        $this->makeNode(101);
        $this->makeNode(102);

        $pelican = Mockery::mock(PelicanApplicationService::class);
        $pelican->shouldReceive('listNodeAllocations')
            ->with(101, Mockery::any())
            ->andThrow(new \RuntimeException('network blip'));
        $pelican->shouldReceive('listNodeAllocations')
            ->with(102, Mockery::any())
            ->andReturn([]);

        $report = (new AllocationMirrorBackfiller($pelican))->run();

        $this->assertSame(1, $report['errors'], 'one node failed but the other ran through');
        $this->assertSame(0, $report['written']);
    }

    private function makeNode(int $pelicanNodeId): Node
    {
        return Node::create([
            'pelican_node_id' => $pelicanNodeId,
            'name' => 'node-'.$pelicanNodeId,
            'fqdn' => 'node-'.$pelicanNodeId.'.test',
            'memory' => 8192,
            'disk' => 102400,
        ]);
    }

    private function makeServer(int $pelicanServerId): Server
    {
        $user = User::create([
            'name' => 'Owner '.$pelicanServerId,
            'email' => 'owner-'.$pelicanServerId.'@test.local',
            'password' => Hash::make('x'),
        ]);

        return Server::create([
            'user_id' => $user->id,
            'pelican_server_id' => $pelicanServerId,
            'name' => 'srv-'.$pelicanServerId,
            'identifier' => 'ident-'.$pelicanServerId,
            'status' => 'active',
        ]);
    }

    private function alloc(int $id, ?int $serverId): PelicanAllocation
    {
        return new PelicanAllocation(
            id: $id,
            ip: '10.0.0.1',
            ipAlias: null,
            port: 25000 + $id,
            notes: null,
            assigned: $serverId !== null,
            serverId: $serverId,
        );
    }

    /** @param array<int, PelicanAllocation> $allocations */
    private function mockPelicanWith(array $allocations): PelicanApplicationService
    {
        $mock = Mockery::mock(PelicanApplicationService::class);
        $mock->shouldReceive('listNodeAllocations')->andReturn($allocations);

        return $mock;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
