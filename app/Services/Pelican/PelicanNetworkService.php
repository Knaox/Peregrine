<?php

namespace App\Services\Pelican;

use App\Services\Pelican\Concerns\MakesClientRequests;
use Illuminate\Http\Client\RequestException;

class PelicanNetworkService
{
    use MakesClientRequests;

    /**
     * List all network allocations for a server.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws RequestException
     */
    public function listAllocations(string $serverIdentifier): array
    {
        $response = $this->request()
            ->get("/api/client/servers/{$serverIdentifier}/network/allocations")
            ->throw();

        return $response->json('data') ?? [];
    }

    /**
     * Auto-assign a new allocation from the available pool.
     *
     * @return array<string, mixed>
     */
    public function addAllocation(string $serverIdentifier): array
    {
        $response = $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/network/allocations")
            ->throw();

        return $response->json('data') ?? $response->json();
    }

    /**
     * Update the notes for an allocation.
     *
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function updateAllocationNotes(string $serverIdentifier, int $allocationId, string $notes): array
    {
        $response = $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/network/allocations/{$allocationId}", [
                'notes' => $notes,
            ])
            ->throw();

        return $response->json('attributes') ?? $response->json();
    }

    /**
     * Set an allocation as the primary allocation.
     *
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function setPrimaryAllocation(string $serverIdentifier, int $allocationId): array
    {
        $response = $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/network/allocations/{$allocationId}/primary")
            ->throw();

        return $response->json('attributes') ?? $response->json();
    }

    /**
     * Delete an allocation from a server.
     *
     * @throws RequestException
     */
    public function deleteAllocation(string $serverIdentifier, int $allocationId): void
    {
        $this->request()
            ->delete("/api/client/servers/{$serverIdentifier}/network/allocations/{$allocationId}")
            ->throw();
    }
}
