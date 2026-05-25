<?php

namespace App\Services\Pelican;

use App\Services\Pelican\Concerns\MakesClientRequests;
use Illuminate\Http\Client\RequestException;

class PelicanDatabaseService
{
    use MakesClientRequests;

    /**
     * List all databases for a server.
     *
     * @param  bool  $includePassword  Ask Pelican for the plaintext password too.
     *                                 It is only returned when explicitly requested,
     *                                 nested under attributes.relationships.password.
     * @return array<int, array<string, mixed>>
     *
     * @throws RequestException
     */
    public function listDatabases(string $serverIdentifier, bool $includePassword = false): array
    {
        $url = "/api/client/servers/{$serverIdentifier}/databases";

        if ($includePassword) {
            $url .= '?include=password';
        }

        $response = $this->request()
            ->get($url)
            ->throw();

        return $response->json('data') ?? [];
    }

    /**
     * Create a new database for a server.
     *
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function createDatabase(string $serverIdentifier, string $database, string $remote = '%'): array
    {
        $response = $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/databases", [
                'database' => $database,
                'remote' => $remote,
            ])
            ->throw();

        return $response->json('attributes') ?? $response->json();
    }

    /**
     * Rotate the password for a database.
     *
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function rotateDatabasePassword(string $serverIdentifier, string $databaseId): array
    {
        $response = $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/databases/{$databaseId}/rotate-password")
            ->throw();

        return $response->json('attributes') ?? $response->json();
    }

    /**
     * Delete a database from a server.
     *
     * @throws RequestException
     */
    public function deleteDatabase(string $serverIdentifier, string $databaseId): void
    {
        $this->request()
            ->delete("/api/client/servers/{$serverIdentifier}/databases/{$databaseId}")
            ->throw();
    }
}
