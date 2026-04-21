<?php

namespace App\Services;

use App\Services\DTOs\SyncComparison;
use App\Services\Sync\InfrastructureSync;
use App\Services\Sync\ServerSync;
use App\Services\Sync\UserSync;

/**
 * Façade preserving the original SyncService public API.
 *
 * Internally delegates to the three domain-specific sub-services under
 * `App\Services\Sync\`. All existing call sites (CLI commands + Filament
 * ListX pages, 14 total) keep using `app(SyncService::class)->method()`
 * without any change.
 */
class SyncService
{
    public function __construct(
        private UserSync $userSync,
        private ServerSync $serverSync,
        private InfrastructureSync $infrastructureSync,
    ) {}

    // Users ---------------------------------------------------------------

    public function compareUsers(): SyncComparison
    {
        return $this->userSync->compareUsers();
    }

    /** @param int[] $pelicanUserIds */
    public function importUsers(array $pelicanUserIds): int
    {
        return $this->userSync->importUsers($pelicanUserIds);
    }

    // Servers -------------------------------------------------------------

    public function compareServers(): SyncComparison
    {
        return $this->serverSync->compareServers();
    }

    /** @param array<int, array{pelican_server_id: int, user_id: int}> $serverImports */
    public function importServers(array $serverImports): int
    {
        return $this->serverSync->importServers($serverImports);
    }

    // Infrastructure ------------------------------------------------------

    public function syncEggs(): int
    {
        return $this->infrastructureSync->syncEggs();
    }

    public function syncNodes(): int
    {
        return $this->infrastructureSync->syncNodes();
    }

    /**
     * @return array{users: SyncComparison, servers: SyncComparison, nodes_synced: int, eggs_synced: int}
     */
    public function healthCheck(): array
    {
        return $this->infrastructureSync->healthCheck($this->userSync, $this->serverSync);
    }
}
