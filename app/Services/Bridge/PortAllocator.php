<?php

namespace App\Services\Bridge;

use App\Exceptions\Bridge\PortAllocationException;
use App\Services\Pelican\DTOs\PelicanAllocation;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Support\Facades\Log;

/**
 * Finds blocks of consecutive free ports on a Pelican node, used by the
 * Bridge ProvisioningService when creating a new server. Some game eggs need
 * multiple consecutive ports (e.g. base + telnet + RCON at base+1/+2) and the
 * Pelican Application API doesn't expose this primitive.
 *
 * Algorithm :
 *  1. List allocations on the node (paginated).
 *  2. Filter assigned=false → free ports only.
 *  3. If a preferred range is given, try inside it first. If no block fits,
 *     log a warning and fall back to the full free pool.
 *  4. Group ports by IP (consecutive block must share the same IP).
 *  5. Sort each group by port number, slide a window of `count` and return
 *     the first contiguous match.
 *
 * Throws PortAllocationException when no block fits.
 */
class PortAllocator
{
    public function __construct(
        private readonly PelicanApplicationService $pelican,
    ) {}

    /**
     * @param  array{0:int,1:int}|null  $preferredRange  [min, max] (inclusive)
     * @return array<int, PelicanAllocation>             Returned in port-ascending order
     *
     * @throws PortAllocationException
     */
    public function findConsecutiveFreePorts(int $nodeId, int $count, ?array $preferredRange = null): array
    {
        if ($count < 1) {
            throw new PortAllocationException("count must be >= 1, got {$count}");
        }

        $allocations = $this->pelican->listNodeAllocations($nodeId);
        $free = array_values(array_filter($allocations, fn (PelicanAllocation $a): bool => ! $a->assigned));

        Log::debug('Bridge PortAllocator: node allocations fetched', [
            'node_id' => $nodeId,
            'count_requested' => $count,
            'total_returned_by_pelican' => count($allocations),
            'free' => count($free),
            'assigned' => count($allocations) - count($free),
            'sample' => array_map(
                fn (PelicanAllocation $a): array => ['id' => $a->id, 'ip' => $a->ip, 'port' => $a->port, 'assigned' => $a->assigned],
                array_slice($allocations, 0, 5),
            ),
        ]);

        if ($preferredRange !== null) {
            $candidates = array_values(array_filter(
                $free,
                fn (PelicanAllocation $a): bool => $a->port >= $preferredRange[0] && $a->port <= $preferredRange[1],
            ));
            $found = $this->findBlock($candidates, $count);
            if ($found !== null) {
                return $found;
            }
            Log::warning('Bridge PortAllocator: no consecutive block in preferred range, falling back', [
                'node_id' => $nodeId,
                'count' => $count,
                'preferred_range' => $preferredRange,
            ]);
        }

        $found = $this->findBlock($free, $count);
        if ($found === null) {
            throw PortAllocationException::noConsecutiveBlock($nodeId, $count, $preferredRange);
        }

        return $found;
    }

    /**
     * @param  array<int, PelicanAllocation>  $candidates
     * @return array<int, PelicanAllocation>|null
     */
    private function findBlock(array $candidates, int $count): ?array
    {
        // Group by IP — consecutive ports on different IPs aren't usable together.
        $byIp = [];
        foreach ($candidates as $allocation) {
            $byIp[$allocation->ip] ??= [];
            $byIp[$allocation->ip][] = $allocation;
        }

        foreach ($byIp as $ipAllocations) {
            usort($ipAllocations, fn (PelicanAllocation $a, PelicanAllocation $b): int => $a->port <=> $b->port);

            $window = [];
            foreach ($ipAllocations as $allocation) {
                if ($window === []) {
                    $window[] = $allocation;
                    continue;
                }

                $previous = end($window);
                if ($allocation->port === $previous->port + 1) {
                    $window[] = $allocation;
                    if (count($window) === $count) {
                        return $window;
                    }
                } else {
                    $window = [$allocation];
                }
            }
        }

        return null;
    }
}
