<?php

namespace App\Services\Pelican;

use App\Services\Pelican\DTOs\PelicanEgg;
use App\Services\Pelican\DTOs\PelicanNest;
use App\Services\Pelican\DTOs\PelicanNode;
use App\Services\Pelican\DTOs\PelicanServer;
use App\Services\Pelican\DTOs\PelicanUser;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class PelicanApplicationService
{
    // -------------------------------------------------------------------------
    // Users
    // -------------------------------------------------------------------------

    /**
     * Create a new user on the Pelican panel.
     *
     * @throws RequestException
     */
    public function createUser(string $email, string $username, string $name): PelicanUser
    {
        $response = $this->request()
            ->post('/api/application/users', [
                'email' => $email,
                'username' => $username,
                'name' => $name,
            ])
            ->throw();

        return PelicanUser::fromApiResponse($response->json());
    }

    /**
     * Delete a user from the Pelican panel.
     *
     * @throws RequestException
     */
    public function deleteUser(int $pelicanUserId): void
    {
        $this->request()
            ->delete("/api/application/users/{$pelicanUserId}")
            ->throw();
    }

    /**
     * Update a user on the Pelican panel.
     *
     * @param array<string, mixed> $data
     *
     * @throws RequestException
     */
    public function updateUser(int $pelicanUserId, array $data): PelicanUser
    {
        $response = $this->request()
            ->patch("/api/application/users/{$pelicanUserId}", $data)
            ->throw();

        return PelicanUser::fromApiResponse($response->json());
    }

    /**
     * Get a single user by their Pelican ID.
     *
     * @throws RequestException
     */
    public function getUser(int $pelicanUserId): PelicanUser
    {
        $response = $this->request()
            ->get("/api/application/users/{$pelicanUserId}")
            ->throw();

        return PelicanUser::fromApiResponse($response->json());
    }

    /**
     * List all users from the Pelican panel.
     *
     * @return PelicanUser[]
     *
     * @throws RequestException
     */
    public function listUsers(): array
    {
        return $this->fetchAllPages('/api/application/users', PelicanUser::class);
    }

    /**
     * Find a user by email on the Pelican panel.
     *
     * @throws RequestException
     */
    public function findUserByEmail(string $email): ?PelicanUser
    {
        $response = $this->request()
            ->get('/api/application/users', ['filter[email]' => $email])
            ->throw();

        $data = $response->json('data') ?? [];

        if (empty($data)) {
            return null;
        }

        return PelicanUser::fromApiResponse($data[0]);
    }

    // -------------------------------------------------------------------------
    // Servers
    // -------------------------------------------------------------------------

    /**
     * Create a new server on the Pelican panel.
     *
     * @throws RequestException
     */
    public function createServer(
        int $userId,
        int $eggId,
        int $nestId,
        int $ram,
        int $cpu,
        int $disk,
        int $nodeId,
        string $name,
    ): PelicanServer {
        $response = $this->request()
            ->post('/api/application/servers', [
                'name' => $name,
                'user' => $userId,
                'egg' => $eggId,
                'nest' => $nestId,
                'docker_image' => '~',
                'startup' => '~',
                'limits' => [
                    'memory' => $ram,
                    'swap' => 0,
                    'disk' => $disk,
                    'io' => 500,
                    'cpu' => $cpu,
                ],
                'feature_limits' => [
                    'databases' => 0,
                    'allocations' => 1,
                    'backups' => 0,
                ],
                'deploy' => [
                    'locations' => [],
                    'dedicated_ip' => false,
                    'port_range' => [],
                ],
                'allocation' => [
                    'default' => null,
                ],
                'node_id' => $nodeId,
            ])
            ->throw();

        return PelicanServer::fromApiResponse($response->json());
    }

    /**
     * Suspend a server on the Pelican panel.
     *
     * @throws RequestException
     */
    public function suspendServer(int $pelicanServerId): void
    {
        $this->request()
            ->post("/api/application/servers/{$pelicanServerId}/suspend")
            ->throw();
    }

    /**
     * Unsuspend a server on the Pelican panel.
     *
     * @throws RequestException
     */
    public function unsuspendServer(int $pelicanServerId): void
    {
        $this->request()
            ->post("/api/application/servers/{$pelicanServerId}/unsuspend")
            ->throw();
    }

    /**
     * Delete a server from the Pelican panel.
     *
     * @throws RequestException
     */
    public function deleteServer(int $pelicanServerId): void
    {
        $this->request()
            ->delete("/api/application/servers/{$pelicanServerId}")
            ->throw();
    }

    /**
     * Get a single server by its Pelican ID.
     *
     * @throws RequestException
     */
    public function getServer(int $pelicanServerId): PelicanServer
    {
        $response = $this->request()
            ->get("/api/application/servers/{$pelicanServerId}")
            ->throw();

        return PelicanServer::fromApiResponse($response->json());
    }

    /**
     * List servers from the Pelican panel, optionally filtered by user.
     *
     * @return PelicanServer[]
     *
     * @throws RequestException
     */
    public function listServers(?int $userId = null): array
    {
        $query = $userId !== null ? ['filter[user]' => $userId] : [];

        return $this->fetchAllPages('/api/application/servers', PelicanServer::class, $query);
    }

    // -------------------------------------------------------------------------
    // Nodes
    // -------------------------------------------------------------------------

    /**
     * List all nodes from the Pelican panel.
     *
     * @return PelicanNode[]
     *
     * @throws RequestException
     */
    public function listNodes(): array
    {
        return $this->fetchAllPages('/api/application/nodes', PelicanNode::class);
    }

    /**
     * Get a single node by its Pelican ID.
     *
     * @throws RequestException
     */
    public function getNode(int $nodeId): PelicanNode
    {
        $response = $this->request()
            ->get("/api/application/nodes/{$nodeId}")
            ->throw();

        return PelicanNode::fromApiResponse($response->json());
    }

    /**
     * Delete a node from the Pelican panel.
     *
     * @throws RequestException
     */
    public function deleteNode(int $pelicanNodeId): void
    {
        $this->request()
            ->delete("/api/application/nodes/{$pelicanNodeId}")
            ->throw();
    }

    // -------------------------------------------------------------------------
    // Eggs
    // -------------------------------------------------------------------------

    /**
     * List all eggs from the Pelican panel.
     * Pelican no longer has nests — eggs are listed directly.
     *
     * @return PelicanEgg[]
     *
     * @throws RequestException
     */
    public function listEggs(): array
    {
        return $this->fetchAllPages('/api/application/eggs', PelicanEgg::class);
    }

    /**
     * Get a single egg by its Pelican ID.
     *
     * @throws RequestException
     */
    public function getEgg(int $eggId): PelicanEgg
    {
        $response = $this->request()
            ->get("/api/application/eggs/{$eggId}")
            ->throw();

        return PelicanEgg::fromApiResponse($response->json());
    }

    /**
     * Delete an egg from the Pelican panel.
     *
     * @throws RequestException
     */
    public function deleteEgg(int $pelicanEggId): void
    {
        $this->request()
            ->delete("/api/application/eggs/{$pelicanEggId}")
            ->throw();
    }

    /**
     * Update a user's password on the Pelican panel.
     *
     * @throws RequestException
     */
    public function updateUserPassword(int $pelicanUserId, string $password): void
    {
        $user = $this->getUser($pelicanUserId);
        $this->request()
            ->patch("/api/application/users/{$pelicanUserId}", [
                'email' => $user->email,
                'username' => $user->username,
                'name' => $user->name,
                'password' => $password,
            ])
            ->throw();
    }

    // Note: Pelican removed the /nests API. Nests are derived from eggs during sync.

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function baseUrl(): string
    {
        return rtrim((string) config('panel.pelican.url'), '/');
    }

    private function apiKey(): string
    {
        return (string) config('panel.pelican.admin_api_key');
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey(),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])
            ->retry(3, 100)
            ->baseUrl($this->baseUrl());
    }

    /**
     * Fetch all pages for a paginated endpoint and map each item through a DTO.
     *
     * @template T
     *
     * @param class-string<T> $dtoClass
     * @param array<string, mixed> $query
     *
     * @return T[]
     *
     * @throws RequestException
     */
    private function fetchAllPages(string $endpoint, string $dtoClass, array $query = []): array
    {
        $items = [];
        $page = 1;

        do {
            $response = $this->request()
                ->get($endpoint, array_merge($query, ['page' => $page]))
                ->throw();

            $json = $response->json();
            $data = $json['data'] ?? [];

            foreach ($data as $item) {
                $items[] = $dtoClass::fromApiResponse($item);
            }

            $totalPages = $json['meta']['pagination']['total_pages'] ?? 1;
            $page++;
        } while ($page <= $totalPages);

        return $items;
    }
}
