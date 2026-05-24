<?php

declare(strict_types=1);

namespace App\Actions\Pelican;

use App\Models\Server;
use App\Services\Pelican\PelicanClientService;
use Throwable;

/**
 * Hard power-cycle a server: kill → wait until Wings reports it OFFLINE →
 * start. A plain `restart` signal isn't enough for our quick-fixes:
 *
 *  - a Docker-image switch only takes effect when Wings RECREATES the
 *    container, which happens on a fresh start from a fully-stopped state;
 *  - after an EULA crash the process has already exited, so we must confirm
 *    it's down before starting again.
 *
 * Mirrors the proven version-changer plugin sequence (kill tolerated when the
 * server is already offline, poll `current_state` until offline, bounded by a
 * timeout). The kill is forceful so the offline state is normally reached in a
 * second or two.
 */
final readonly class RestartServerCleanlyAction
{
    public function __construct(
        private PelicanClientService $client,
    ) {}

    public function __invoke(Server $server, int $maxWaitSeconds = 30, int $intervalMs = 1000): void
    {
        $identifier = $server->identifier;

        // Kill. An already-offline server answers 4xx — not fatal, the wait
        // below confirms the real state.
        try {
            $this->client->setPowerState($identifier, 'kill');
        } catch (Throwable) {
            // ignore — confirmed by the offline poll
        }

        $deadline = microtime(true) + $maxWaitSeconds;
        while (microtime(true) < $deadline) {
            if ($this->isOffline($identifier)) {
                break;
            }
            usleep($intervalMs * 1000);
        }

        // Start fresh — Wings recreates the container (and pulls the new image
        // when one was just set).
        $this->client->setPowerState($identifier, 'start');
    }

    private function isOffline(string $identifier): bool
    {
        try {
            return $this->client->getServerResources($identifier)->state === 'offline';
        } catch (Throwable) {
            return false;
        }
    }
}
