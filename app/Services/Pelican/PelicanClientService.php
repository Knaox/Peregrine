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
    public function listFiles(string $serverIdentifier, string $directory = '/'): array
    {
        $response = $this->request()
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
    public function getFileContent(string $serverIdentifier, string $filePath): string
    {
        $response = $this->request()
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
    public function renameFile(string $serverIdentifier, string $from, string $to): void
    {
        $root = dirname($from);

        $this->request()
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
    public function deleteFile(string $serverIdentifier, string $filePath): void
    {
        $root = dirname($filePath);

        $this->request()
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
    public function compressFiles(string $serverIdentifier, array $files): void
    {
        $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/files/compress", [
                'root' => '/',
                'files' => $files,
            ])
            ->throw();
    }

    /**
     * Write content to a file on the server.
     * Note: Pelican expects raw text body, not JSON.
     *
     * @throws RequestException
     */
    public function writeFile(string $serverIdentifier, string $filePath, string $content): void
    {
        Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->clientApiKey(),
            'Accept' => 'application/json',
        ])
            ->withBody($content, 'text/plain')
            ->retry(3, 100)
            ->baseUrl($this->baseUrl())
            ->post("/api/client/servers/{$serverIdentifier}/files/write?file=" . urlencode($filePath))
            ->throw();
    }

    /**
     * Decompress an archive on the server.
     *
     * @throws RequestException
     */
    public function decompressFiles(string $serverIdentifier, string $file): void
    {
        $this->request()
            ->timeout(300)
            ->post("/api/client/servers/{$serverIdentifier}/files/decompress", [
                'root' => dirname($file) === '.' ? '/' : dirname($file),
                'file' => basename($file),
            ])
            ->throw();
    }

    /**
     * Get a signed upload URL for a server.
     *
     * @throws RequestException
     */
    public function getUploadUrl(string $serverIdentifier): string
    {
        $response = $this->request()
            ->get("/api/client/servers/{$serverIdentifier}/files/upload")
            ->throw();

        return $response->json('attributes.url') ?? '';
    }

    /**
     * Create a new folder on the server.
     *
     * @throws RequestException
     */
    public function createFolder(string $serverIdentifier, string $root, string $name): void
    {
        $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/files/create-folder", [
                'root' => $root,
                'name' => $name,
            ])
            ->throw();
    }

    // -------------------------------------------------------------------------
    // Startup variables
    // -------------------------------------------------------------------------

    /**
     * Get startup variables for a server.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws RequestException
     */
    public function getStartupVariables(string $serverIdentifier): array
    {
        $response = $this->request()
            ->get("/api/client/servers/{$serverIdentifier}/startup")
            ->throw();

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

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function baseUrl(): string
    {
        return rtrim((string) config('panel.pelican.url'), '/');
    }

    private function clientApiKey(): string
    {
        return (string) config('panel.pelican.client_api_key');
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->clientApiKey(),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])
            ->retry(3, 100)
            ->baseUrl($this->baseUrl());
    }
}
