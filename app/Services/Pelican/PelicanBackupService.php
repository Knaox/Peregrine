<?php

namespace App\Services\Pelican;

use App\Services\Pelican\Concerns\MakesClientRequests;
use Illuminate\Http\Client\RequestException;

class PelicanBackupService
{
    use MakesClientRequests;

    /**
     * List all backups for a server.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws RequestException
     */
    public function listBackups(string $serverIdentifier): array
    {
        $response = $this->request()
            ->get("/api/client/servers/{$serverIdentifier}/backups")
            ->throw();

        return $response->json('data') ?? [];
    }

    /**
     * Create a new backup for a server.
     *
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function createBackup(
        string $serverIdentifier,
        ?string $name = null,
        ?string $ignored = null,
        bool $isLocked = false,
    ): array {
        $response = $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/backups", [
                'name' => $name,
                'ignored' => $ignored,
                'is_locked' => $isLocked,
            ])
            ->throw();

        return $response->json('attributes') ?? $response->json();
    }

    /**
     * Get a signed download URL for a backup.
     *
     * @throws RequestException
     */
    public function getBackupDownloadUrl(string $serverIdentifier, string $backupId): string
    {
        $response = $this->request()
            ->get("/api/client/servers/{$serverIdentifier}/backups/{$backupId}/download")
            ->throw();

        return $response->json('attributes.url') ?? '';
    }

    /**
     * Toggle the lock status of a backup.
     *
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function toggleBackupLock(string $serverIdentifier, string $backupId): array
    {
        $response = $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/backups/{$backupId}/lock")
            ->throw();

        return $response->json('attributes') ?? $response->json();
    }

    /**
     * Restore a backup to the server.
     *
     * @throws RequestException
     */
    public function restoreBackup(string $serverIdentifier, string $backupId, bool $truncate = false): void
    {
        $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/backups/{$backupId}/restore", [
                'truncate' => $truncate,
            ])
            ->throw();
    }

    /**
     * Delete a backup from a server.
     *
     * @throws RequestException
     */
    public function deleteBackup(string $serverIdentifier, string $backupId): void
    {
        $this->request()
            ->delete("/api/client/servers/{$serverIdentifier}/backups/{$backupId}")
            ->throw();
    }
}
