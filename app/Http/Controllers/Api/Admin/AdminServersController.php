<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Pelican\ResolveServerNodeAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminServerResource;
use App\Models\Server;
use App\Services\Wings\NodeHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Admin-only endpoint that returns ALL servers with their owner info.
 *
 * Authorization is layered: the `admin` middleware ensures `is_admin`, the
 * `two-factor` middleware ensures 2FA is set up when `auth_2fa_required_admins`
 * is enabled. Resource-level checks (ServerPolicy) are bypassed for admins
 * via the scoped Gate::before (AuthServiceProvider, plan §S5).
 */
class AdminServersController extends Controller
{
    public function index(
        Request $request,
        ResolveServerNodeAction $resolveNode,
        NodeHealthService $health,
    ): JsonResponse {
        $query = Server::query()->with(['user:id,name,email', 'egg', 'serverConfiguration', 'node']);

        if ($search = $request->query('search')) {
            $search = (string) $search;
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('identifier', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', (string) $status);
        }

        if ($userId = $request->query('user_id')) {
            $query->where('user_id', (int) $userId);
        }

        $perPage = (int) $request->query('per_page', '25');
        $perPage = max(1, min($perPage, 100));

        $paginator = $query->orderBy('name')->paginate($perPage);

        $this->hydrateNodeLinks($paginator->getCollection(), $resolveNode);
        $this->scheduleNodeProbes($paginator->getCollection(), $health);

        return AdminServerResource::collection($paginator)->response();
    }

    /**
     * Backfill missing server→node links, bounded to a handful per request so
     * a large un-migrated fleet converges over a few page loads instead of
     * hammering Pelican in one go.
     *
     * @param  Collection<int, Server>  $servers
     */
    private function hydrateNodeLinks(Collection $servers, ResolveServerNodeAction $resolveNode): void
    {
        $missing = $servers
            ->filter(fn (Server $s) => $s->node_id === null && $s->pelican_server_id !== null)
            ->take(5);

        if ($missing->isEmpty()) {
            return;
        }

        $missing->each(fn (Server $s) => $resolveNode($s));
        $servers->load('node');
    }

    /**
     * The list renders node health from cache only (peekNode) — a dead node
     * must never stall the page with probe timeouts. Un-probed nodes are
     * checked AFTER the response is sent, so the next load has fresh data.
     *
     * @param  Collection<int, Server>  $servers
     */
    private function scheduleNodeProbes(Collection $servers, NodeHealthService $health): void
    {
        $unprobed = $servers->pluck('node')
            ->filter()
            ->unique('id')
            ->filter(fn ($node) => $health->peekNode($node) === null)
            ->values();

        if ($unprobed->isEmpty()) {
            return;
        }

        defer(fn () => $unprobed->each(fn ($node) => $health->checkNode($node)));
    }
}
