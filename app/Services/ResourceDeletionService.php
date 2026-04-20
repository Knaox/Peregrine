<?php

namespace App\Services;

use App\Models\Egg;
use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Unified 3-way deletion strategy for synced Pelican resources.
 *
 * Strategies:
 *   - 'peregrine' → drop only the local record, leave the remote resource alone.
 *   - 'pelican'   → call Pelican DELETE, keep the local row so admin can re-sync later.
 *   - 'both'      → delete remotely first, then locally (default + safest).
 *
 * Remote failures bubble up so the Filament action surfaces a notification and
 * the local row is NOT removed (avoids ghost state).
 */
class ResourceDeletionService
{
    public const STRATEGY_PEREGRINE = 'peregrine';
    public const STRATEGY_PELICAN = 'pelican';
    public const STRATEGY_BOTH = 'both';

    public function __construct(
        private PelicanApplicationService $pelican,
    ) {}

    public function delete(Model $resource, string $strategy): void
    {
        if (! in_array($strategy, [self::STRATEGY_PEREGRINE, self::STRATEGY_PELICAN, self::STRATEGY_BOTH], true)) {
            throw new InvalidArgumentException("Unknown deletion strategy: {$strategy}");
        }

        $deleteRemote = in_array($strategy, [self::STRATEGY_PELICAN, self::STRATEGY_BOTH], true);
        $deleteLocal = in_array($strategy, [self::STRATEGY_PEREGRINE, self::STRATEGY_BOTH], true);

        if ($deleteRemote) {
            $this->deleteRemote($resource);
        }

        if ($deleteLocal) {
            $resource->delete();
        }
    }

    private function deleteRemote(Model $resource): void
    {
        $pelicanId = $this->pelicanIdOf($resource);

        // No remote id → nothing to delete on Pelican. Silent no-op so bulk
        // actions over mixed records don't fail on locally-created entries.
        if ($pelicanId === null) {
            return;
        }

        try {
            match (true) {
                $resource instanceof User => $this->pelican->deleteUser($pelicanId),
                $resource instanceof Server => $this->pelican->deleteServer($pelicanId),
                $resource instanceof Egg => $this->pelican->deleteEgg($pelicanId),
                $resource instanceof Node => $this->pelican->deleteNode($pelicanId),
                default => throw new InvalidArgumentException('Unsupported resource: ' . $resource::class),
            };
        } catch (RequestException $e) {
            Log::warning('Pelican delete failed', [
                'resource' => $resource::class,
                'id' => $resource->getKey(),
                'pelican_id' => $pelicanId,
                'status' => $e->response?->status(),
            ]);

            throw $e;
        }
    }

    private function pelicanIdOf(Model $resource): ?int
    {
        $value = match (true) {
            $resource instanceof User => $resource->pelican_user_id,
            $resource instanceof Server => $resource->pelican_server_id,
            $resource instanceof Egg => $resource->pelican_egg_id,
            $resource instanceof Node => $resource->pelican_node_id,
            default => null,
        };

        return $value !== null ? (int) $value : null;
    }
}
