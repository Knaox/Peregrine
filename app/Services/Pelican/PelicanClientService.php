<?php

namespace App\Services\Pelican;

use App\Services\Pelican\DTOs\PelicanServer;
use App\Services\Pelican\DTOs\ServerResources;
use App\Services\Pelican\DTOs\WebsocketCredentials;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class PelicanClientService
{
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
    public function listServers(string $apiKey): array
    {
        $items = [];
        $page = 1;

        do {
            $response = $this->request($apiKey)
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
    public function getServer(string $apiKey, string $serverIdentifier): PelicanServer
    {
        $response = $this->request($apiKey)
            ->get("/api/client/servers/{$serverIdentifier}")
            ->throw();

        return PelicanServer::fromApiResponse($response->json());
    }

    /**
     * Get current resource usage for a server.
     *
     * @throws RequestException
     */
    public function getServerResources(string $apiKey, string $serverIdentifier): ServerResources
    {
        $response = $this->request($apiKey)
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
    public function sendCommand(string $apiKey, string $serverIdentifier, string $command): void
    {
        $this->request($apiKey)
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
    public function setPowerState(string $apiKey, string $serverIdentifier, string $signal): void
    {
        $this->request($apiKey)
            ->post("/api/client/servers/{$serverIdentifier}/power", [
                'signal' => $signal,
            ])
            ->throw();
    }

    // -------------------------------------------------------------------------
    // File management
    // -------------------------------------------------------------------------

    /**
     * List files in a directory on the server.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws RequestException
     */
    public function listFiles(string $apiKey, string $serverIdentifier, string $directory = '/'): array
    {
        $response = $this->request($apiKey)
            ->get("/api/client/servers/{$serverIdentifier}/files/list", [
                'directory' => $directory,
            ])
            ->throw();

        return $response->json('data') ?? [];
    }

    /**
     * Get the contents of a file on the server.
     *
     * @throws RequestException
     */
    public function getFileContent(string $apiKey, string $serverIdentifier, string $filePath): string
    {
        $response = $this->request($apiKey)
            ->get("/api/client/servers/{$serverIdentifier}/files/contents", [
                'file' => $filePath,
            ])
            ->throw();

        return $response->body();
    }

    /**
     * Rename (or move) a file on the server.
     *
     * @throws RequestException
     */
    public function renameFile(string $apiKey, string $serverIdentifier, string $from, string $to): void
    {
        $root = dirname($from);

        $this->request($apiKey)
            ->put("/api/client/servers/{$serverIdentifier}/files/rename", [
                'root' => $root === '.' ? '/' : $root,
                'files' => [
                    [
                        'from' => basename($from),
                        'to' => $to,
                    ],
                ],
            ])
            ->throw();
    }

    /**
     * Delete a file on the server.
     *
     * @throws RequestException
     */
    public function deleteFile(string $apiKey, string $serverIdentifier, string $filePath): void
    {
        $root = dirname($filePath);

        $this->request($apiKey)
            ->post("/api/client/servers/{$serverIdentifier}/files/delete", [
                'root' => $root === '.' ? '/' : $root,
                'files' => [basename($filePath)],
            ])
            ->throw();
    }

    /**
     * Compress files on the server.
     *
     * @param string[] $files
     *
     * @throws RequestException
     */
    public function compressFiles(string $apiKey, string $serverIdentifier, array $files): void
    {
        $this->request($apiKey)
            ->post("/api/client/servers/{$serverIdentifier}/files/compress", [
                'root' => '/',
                'files' => $files,
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
    public function getWebsocket(string $apiKey, string $serverIdentifier): WebsocketCredentials
    {
        $response = $this->request($apiKey)
            ->get("/api/client/servers/{$serverIdentifier}/websocket")
            ->throw();

        return WebsocketCredentials::fromApiResponse($response->json());
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function baseUrl(): string
    {
        return rtrim((string) config('panel.pelican.url'), '/');
    }

    private function request(string $apiKey): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])
            ->retry(3, 100)
            ->baseUrl($this->baseUrl());
    }
}
