<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Jobs;

use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Plugins\PeregrinePlayerCounter\PlayerCounterServiceProvider;
use Plugins\PeregrinePlayerCounter\Services\QueryAccessResolver;

/**
 * Runs the query-port auto-resolution off the request path (it restarts the
 * server, which is slow). Dispatched by ServerPlayerCountService when a
 * whitelisted, running server's query fails and the port looks unreachable —
 * guarded by an attempt marker so an unresolvable game can't loop. The resolver
 * re-checks reachability itself, so a spurious dispatch is a safe no-op (no
 * restart) rather than a wasted reboot.
 */
class ResolveQueryAccessJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public Server $server) {}

    public function handle(QueryAccessResolver $resolver): void
    {
        $result = $resolver->resolve($this->server);

        if (($result['ok'] ?? false) !== true) {
            Log::info('[player-counter] query-access auto-resolve skipped', [
                'server' => $this->server->id,
                'error' => $result['error'] ?? 'unknown',
            ]);

            return;
        }

        Log::info('[player-counter] query-access auto-resolved', [
            'server' => $this->server->id,
            'kind' => $result['kind'] ?? null,
            'port' => $result['port'] ?? null,
            'variable' => $result['variable'] ?? null,
        ]);
    }

    public function uniqueId(): string
    {
        return PlayerCounterServiceProvider::PLUGIN_ID.':resolve:'.$this->server->id;
    }
}
