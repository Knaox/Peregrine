<?php

namespace App\Services\Pelican;

use App\Services\Pelican\Concerns\MakesClientRequests;
use App\Services\Pelican\DTOs\PelicanServer;
use App\Services\Pelican\DTOs\ServerResources;
use App\Services\Pelican\DTOs\WebsocketCredentials;

class PelicanClientService
{
    use MakesClientRequests;
    // -------------------------------------------------------------------------
    // Servers
    // -------------------------------------------------------------------------

    /**
     * List all servers accessible by the client API key.
     *
     * @return PelicanServer[]
     *
     * @throws RequestException
     */
    public function listServers(): array
    {
        $items = [];
        $page = 1;

        do {
            $response = $this->request()
                ->get('/api/client', ['page' => $page])
                ->throw();

            $json = $response->json();
            $data = $json['data'] ?? [];

            foreach ($data as $item) {
                $items[] = PelicanServer::fromApiResponse($item);
            }

            $totalPages = $json['meta']['pagination']['total_pages'] ?? 1;
            $page++;
        } while ($page <= $totalPages);

        return $items;
    }

    /**
     * Get a single server by its identifier.
     *
     * @throws RequestException
     */
    public function getServer(string $serverIdentifier): PelicanServer
    {
        $response = $this->request()
            ->get("/api/client/servers/{$serverIdentifier}")
            ->throw();

        return PelicanServer::fromApiResponse($response->json());
    }

    /**
     * Get raw server data from Pelican Client API (unstructured array).
     *
     * @return array<string, mixed>
     */
    public function getRawServer(string $serverIdentifier): array
    {
        $response = $this->request()
            ->get("/api/client/servers/{$serverIdentifier}")
            ->throw();

        return $response->json('attributes') ?? $response->json() ?? [];
    }

    /**
     * Get current resource usage for a server.
     *
     * @throws RequestException
     */
    public function getServerResources(string $serverIdentifier): ServerResources
    {
        $response = $this->request()
            ->get("/api/client/servers/{$serverIdentifier}/resources")
            ->throw();

        return ServerResources::fromApiResponse($response->json());
    }

    // -------------------------------------------------------------------------
    // Console & power
    // -------------------------------------------------------------------------

    /**
     * Send a command to the server console.
     *
     * @throws RequestException
     */
    public function sendCommand(string $serverIdentifier, string $command): void
    {
        $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/command", [
                'command' => $command,
            ])
            ->throw();
    }

    /**
     * Set the power state of a server (start, stop, restart, kill).
     *
     * @throws RequestException
     */
    public function setPowerState(string $serverIdentifier, string $signal): void
    {
        $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/power", [
                'signal' => $signal,
            ])
            ->throw();
    }

    /**
     * Rename the server. Updates the display name in Pelican; callers should
     * also update the local Server row so the panel UI stays in sync without
     * a sync cycle.
     *
     * @throws RequestException
     */
    public function renameServer(string $serverIdentifier, string $name): void
    {
        $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/settings/rename", [
                'name' => $name,
            ])
            ->throw();
    }

    /**
     * Trigger a server reinstall (re-runs the egg's install script). Data in
     * the server files is preserved by default — use with caution.
     *
     * @throws RequestException
     */
    public function reinstallServer(string $serverIdentifier): void
    {
        $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/settings/reinstall")
            ->throw();
    }

    // -------------------------------------------------------------------------
    // File operations (used by wipe-and-reinstall flow)
    // -------------------------------------------------------------------------

    /**
     * List entries in a directory of a server's filesystem via the Pelican
     * Client API. Each row carries `name`, `mode`, `is_file`, `is_symlink`,
     * `size`, etc. — we mostly need `name` + `is_file` for the wipe path.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws RequestException
     */
    public function listFiles(string $serverIdentifier, string $directory = '/'): array
    {
        $response = $this->request()
            ->get("/api/client/servers/{$serverIdentifier}/files/list", ['directory' => $directory])
            ->throw();

        $entries = [];
        foreach (($response->json('data') ?? []) as $entry) {
            $attrs = $entry['attributes'] ?? $entry;
            if (! is_array($attrs)) {
                continue;
            }
            $entries[] = $attrs;
        }

        return $entries;
    }

    /**
     * Delete one or more files/directories from a server via the Pelican
     * Client API. Pelican accepts both files and directory names — directories
     * are removed recursively. The `root` is the parent directory, the
     * `files` array is a list of names relative to that root.
     *
     * @param  list<string>  $files
     *
     * @throws RequestException
     */
    public function deleteFiles(string $serverIdentifier, string $root, array $files): void
    {
        if ($files === []) {
            return;
        }

        $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/files/delete", [
                'root' => $root,
                'files' => array_values($files),
            ])
            ->throw();
    }

    /**
     * Wipe the entire /mnt/server volume of a server by listing the root and
     * issuing a single bulk delete. Used by the reinstall flow when the
     * "delete data" option is checked. Does NOT trigger a reinstall — caller
     * follows up with reinstallServer().
     *
     * @throws RequestException
     */
    public function wipeServerFiles(string $serverIdentifier): void
    {
        $entries = $this->listFiles($serverIdentifier, '/');
        $names = [];
        foreach ($entries as $entry) {
            $name = $entry['name'] ?? null;
            if (is_string($name) && $name !== '' && $name !== '.' && $name !== '..') {
                $names[] = $name;
            }
        }

        $this->deleteFiles($serverIdentifier, '/', $names);
    }

    // -------------------------------------------------------------------------
    // Startup variables
    // -------------------------------------------------------------------------

    /**
     * Get startup variables for a server.
     *
     * Returns an empty array when Pelican replies with HTTP 409
     * `ServerStateConflictException` — that's the documented response
     * while the server is mid-install / mid-reinstall (e.g. during a
     * modpack-installer egg swap or a panel-triggered reinstall) and is
     * not an error worth bubbling up. Callers polling this endpoint can
     * just retry once the server settles.
     *
     * Any other HTTP error still throws the underlying `RequestException`.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws RequestException
     */
    public function getStartupVariables(string $serverIdentifier): array
    {
        try {
            $response = $this->request()
                ->get("/api/client/servers/{$serverIdentifier}/startup")
                ->throw();
        } catch (RequestException $e) {
            if ($e->response !== null && $e->response->status() === 409) {
                return [];
            }
            throw $e;
        }

        $data = $response->json('data') ?? [];

        return array_map(fn (array $item) => $item['attributes'] ?? $item, $data);
    }

    /**
     * Update a single startup variable.
     *
     * @throws RequestException
     */
    public function updateStartupVariable(string $serverIdentifier, string $key, string $value): void
    {
        $this->request()
            ->put("/api/client/servers/{$serverIdentifier}/startup/variable", [
                'key' => $key,
                'value' => $value,
            ])
            ->throw();
    }

    // -------------------------------------------------------------------------
    // Websocket
    // -------------------------------------------------------------------------

    /**
     * Get websocket credentials for a server.
     *
     * @throws RequestException
     */
    public function getWebsocket(string $serverIdentifier): WebsocketCredentials
    {
        $response = $this->request()
            ->get("/api/client/servers/{$serverIdentifier}/websocket")
            ->throw();

        return WebsocketCredentials::fromApiResponse($response->json());
    }

}
