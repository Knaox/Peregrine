<?php

namespace Plugins\Invitations\Services;

use App\Services\Pelican\Concerns\MakesClientRequests;

class PelicanSubuserService
{
    use MakesClientRequests;

    /**
     * List subusers for a server.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listSubusers(string $serverIdentifier): array
    {
        $response = $this->request()
            ->get("/api/client/servers/{$serverIdentifier}/users")
            ->throw();

        $data = $response->json('data') ?? [];

        return array_map(
            fn (array $item) => $item['attributes'] ?? $item,
            $data,
        );
    }

    /**
     * Create a subuser on Pelican for a server.
     *
     * @param array<int, string> $permissions
     * @return array<string, mixed>
     */
    public function createSubuser(
        string $serverIdentifier,
        string $email,
        array $permissions,
    ): array {
        $response = $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/users", [
                'email' => $email,
                'permissions' => $permissions,
            ])
            ->throw();

        return $response->json('attributes') ?? $response->json() ?? [];
    }

    /**
     * Update a subuser's permissions.
     *
     * @param array<int, string> $permissions
     * @return array<string, mixed>
     */
    public function updateSubuser(
        string $serverIdentifier,
        string $subuserUuid,
        array $permissions,
    ): array {
        $response = $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/users/{$subuserUuid}", [
                'permissions' => $permissions,
            ])
            ->throw();

        return $response->json('attributes') ?? $response->json() ?? [];
    }

    /**
     * Delete a subuser from a server.
     */
    public function deleteSubuser(string $serverIdentifier, string $subuserUuid): void
    {
        $this->request()
            ->delete("/api/client/servers/{$serverIdentifier}/users/{$subuserUuid}")
            ->throw();
    }
}
