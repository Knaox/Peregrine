<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Pelican;

use App\Models\Server;
use App\Services\Pelican\PelicanClientService;
use Throwable;

/**
 * Stop a server and wait for it to actually report `offline`, then start it
 * again — the safe envelope around applying/restoring boost values. State is
 * polled (default every 5s up to 5 min). On an API error the state is treated
 * as unknown (never "offline"), so a boost is never applied to a server we
 * can't confirm is stopped.
 */
final class PowerHelper
{
    public function __construct(private readonly PelicanClientService $client) {}

    public function stopAndWait(Server $server, int $timeoutSeconds = 300, int $intervalSeconds = 5): bool
    {
        if ($this->state($server) === 'offline') {
            return true;
        }

        try {
            $this->client->setPowerState($server->identifier, 'stop');
        } catch (Throwable) {
            // fall through to polling — the server may already be stopping
        }

        $deadline = time() + $timeoutSeconds;
        while (time() < $deadline) {
            sleep($intervalSeconds);
            if ($this->state($server) === 'offline') {
                return true;
            }
        }

        return false;
    }

    public function start(Server $server): void
    {
        try {
            $this->client->setPowerState($server->identifier, 'start');
        } catch (Throwable) {
            // best-effort — operator can start it manually if the API hiccups
        }
    }

    private function state(Server $server): string
    {
        try {
            return $this->client->getServerResources($server->identifier)->state;
        } catch (Throwable) {
            return 'unknown';
        }
    }
}
