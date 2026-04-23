<?php

namespace Tests\Unit\Bridge;

use App\Exceptions\Bridge\PortAllocationException;
use App\Services\Bridge\PortAllocator;
use App\Services\Pelican\DTOs\PelicanAllocation;
use App\Services\Pelican\PelicanApplicationService;
use Mockery;
use Tests\TestCase;

class PortAllocatorTest extends TestCase
{
    public function test_finds_consecutive_ports_when_available(): void
    {
        $pelican = $this->mockPelicanWith([
            $this->alloc(1, '1.1.1.1', 25565, false),
            $this->alloc(2, '1.1.1.1', 25566, false),
            $this->alloc(3, '1.1.1.1', 25567, false),
        ]);

        $result = (new PortAllocator($pelican))->findConsecutiveFreePorts(nodeId: 1, count: 3);

        $this->assertCount(3, $result);
        $this->assertSame([25565, 25566, 25567], array_map(fn ($a) => $a->port, $result));
    }

    public function test_finds_ports_in_preferred_range_first(): void
    {
        $pelican = $this->mockPelicanWith([
            $this->alloc(1, '1.1.1.1', 8000, false),
            $this->alloc(2, '1.1.1.1', 8001, false),
            $this->alloc(3, '1.1.1.1', 25565, false),
            $this->alloc(4, '1.1.1.1', 25566, false),
        ]);

        $result = (new PortAllocator($pelican))->findConsecutiveFreePorts(
            nodeId: 1, count: 2, preferredRange: [25500, 25600],
        );

        $this->assertSame([25565, 25566], array_map(fn ($a) => $a->port, $result));
    }

    public function test_falls_back_outside_preferred_range_with_warning(): void
    {
        $pelican = $this->mockPelicanWith([
            $this->alloc(1, '1.1.1.1', 8000, false),
            $this->alloc(2, '1.1.1.1', 8001, false),
            // Nothing in 25500-25600 range
        ]);

        $result = (new PortAllocator($pelican))->findConsecutiveFreePorts(
            nodeId: 1, count: 2, preferredRange: [25500, 25600],
        );

        $this->assertSame([8000, 8001], array_map(fn ($a) => $a->port, $result));
    }

    public function test_throws_when_no_consecutive_block(): void
    {
        $pelican = $this->mockPelicanWith([
            $this->alloc(1, '1.1.1.1', 25565, false),
            $this->alloc(2, '1.1.1.1', 25567, false), // gap at 25566
            $this->alloc(3, '1.1.1.1', 25569, false), // gap at 25568
        ]);

        $this->expectException(PortAllocationException::class);
        (new PortAllocator($pelican))->findConsecutiveFreePorts(nodeId: 1, count: 3);
    }

    public function test_ignores_assigned_allocations(): void
    {
        $pelican = $this->mockPelicanWith([
            $this->alloc(1, '1.1.1.1', 25565, false),
            $this->alloc(2, '1.1.1.1', 25566, true),  // assigned, must be ignored
            $this->alloc(3, '1.1.1.1', 25567, false),
            $this->alloc(4, '1.1.1.1', 25568, false),
        ]);

        $result = (new PortAllocator($pelican))->findConsecutiveFreePorts(nodeId: 1, count: 2);

        $this->assertSame([25567, 25568], array_map(fn ($a) => $a->port, $result));
    }

    public function test_consecutive_block_must_share_same_ip(): void
    {
        $pelican = $this->mockPelicanWith([
            $this->alloc(1, '1.1.1.1', 25565, false),
            $this->alloc(2, '2.2.2.2', 25566, false), // different IP, breaks consecutivity
            $this->alloc(3, '1.1.1.1', 25567, false),
        ]);

        $this->expectException(PortAllocationException::class);
        (new PortAllocator($pelican))->findConsecutiveFreePorts(nodeId: 1, count: 2);
    }

    private function alloc(int $id, string $ip, int $port, bool $assigned): PelicanAllocation
    {
        return new PelicanAllocation(
            id: $id, ip: $ip, ipAlias: null, port: $port, notes: null, assigned: $assigned,
        );
    }

    /**
     * @param  array<int, PelicanAllocation>  $allocations
     */
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
