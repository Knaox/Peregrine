<?php

namespace Plugins\Invitations\Services;

use App\Models\Server;
use App\Services\Pelican\Concerns\MakesClientRequests;
use App\Services\SettingsService;
use Plugins\Invitations\Models\PelicanSubuser;

class PelicanSubuserService
{
    use MakesClientRequests;

    /**
     * List subusers for a server.
     *
     * Reads from the local mirror table when `mirror_reads_enabled` is on
     * (Phase 2 — fed by SyncPelicanSubuser listener via core webhooks).
     * Falls back to live Pelican Client API otherwise.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listSubusers(string $serverIdentifier): array
    {
        if ($this->mirrorReadsEnabled()) {
            $local = $this->listSubusersFromMirror($serverIdentifier);
            if ($local !== null) {
                return $local;
            }
        }

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
     * @return array<int, array<string, mixed>>|null  null if the server can't be resolved locally
     */
    private function listSubusersFromMirror(string $serverIdentifier): ?array
    {
        $server = Server::where('identifier', $serverIdentifier)->first();
        if ($server === null || $server->pelican_server_id === null) {
            return null;
        }

        return PelicanSubuser::where('pelican_server_id', $server->pelican_server_id)
            ->orderByDesc('pelican_created_at')
            ->get()
            ->map(fn ($s) => [
                'pelican_subuser_id' => $s->pelican_subuser_id,
                'user_id' => $s->pelican_user_id,
                'permissions' => $s->permissions ?? [],
                'created_at' => $s->pelican_created_at?->toIso8601String(),
            ])
            ->all();
    }

    private function mirrorReadsEnabled(): bool
    {
        $value = (string) app(SettingsService::class)->get('mirror_reads_enabled', 'false');
        return $value === 'true' || $value === '1';
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
