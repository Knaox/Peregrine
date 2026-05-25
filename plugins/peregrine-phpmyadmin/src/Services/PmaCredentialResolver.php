<?php

declare(strict_types=1);

namespace Plugins\PeregrinePhpmyadmin\Services;

use App\Models\Server;
use App\Services\Pelican\Concerns\MakesClientRequests;

/**
 * Resolves the live MySQL credentials for one server database from Pelican,
 * including the plaintext password. Reuses the core client-request trait
 * (Bearer auth + base URL via PelicanCredentials) so no Pelican wiring is
 * duplicated. The password is only returned by Pelican with `?include=password`
 * (nested under relationships.password); we flatten it, with a fallback to an
 * inline `password` key for robustness across Pelican versions.
 */
class PmaCredentialResolver
{
    use MakesClientRequests;

    /**
     * @return array{username: string, password: string, host: string, port: int, database: string}|null
     */
    public function resolve(Server $server, string $databaseId): ?array
    {
        $response = $this->request()
            ->get("/api/client/servers/{$server->identifier}/databases?include=password")
            ->throw();

        foreach ($response->json('data', []) as $row) {
            $attrs = $row['attributes'] ?? $row;

            if ((string) ($attrs['id'] ?? '') !== $databaseId) {
                continue;
            }

            $password = $attrs['relationships']['password']['attributes']['password']
                ?? $attrs['password']
                ?? null;

            if ($password === null) {
                return null;
            }

            return [
                'username' => (string) ($attrs['username'] ?? ''),
                'password' => (string) $password,
                'host' => (string) ($attrs['host']['address'] ?? ''),
                'port' => (int) ($attrs['host']['port'] ?? 3306),
                'database' => (string) ($attrs['name'] ?? ''),
            ];
        }

        return null;
    }
}
