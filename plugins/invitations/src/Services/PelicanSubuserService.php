<?php

namespace Plugins\Invitations\Services;

use App\Services\Pelican\Concerns\MakesClientRequests;

class PelicanSubuserService
{
    use MakesClientRequests;

    /**
     * Permission key prefixes Pelican Panel actually validates on
     * `POST /api/client/servers/{id}/users` and the matching update
     * endpoint. Anything outside this list — Peregrine-specific perms
     * like `overview.*` (added by Invitations on top of Pelican) and
     * plugin-registered perms like `modpack.*` / `arkmods.*` /
     * `arkasamods.*` / `eggconfig.*` — would be rejected as a 422
     * `validation.in` error and the whole call would fail, leaving
     * the subuser with the OLD permissions both on Pelican AND in
     * Peregrine's local pivot.
     *
     * The fix : split the incoming permission list on the way out.
     * Pelican gets only the prefixes it knows ; the rest stay in
     * Peregrine's `server_user` pivot, where the SPA reads them via
     * `listSubusers`'s array_merge logic.
     *
     * Source : pterodactyl/pelican-panel `app/Models/Permission.php`
     * — the list of permissions exposed to client-API subuser
     * endpoints. Updates here MUST be mirrored when Pelican adds a
     * new native permission prefix.
     */
    private const PELICAN_NATIVE_PREFIXES = [
        'websocket',
        'control',
        'user',
        'file',
        'backup',
        'allocation',
        'startup',
        'database',
        'schedule',
        'settings',
        'activity',
    ];

    /**
     * Filter a permission list down to ones Pelican will actually
     * accept on its client-API user endpoints. Returns a re-indexed
     * array (no holes).
     *
     * @param array<int, string> $permissions
     * @return array<int, string>
     */
    public static function filterPelicanNative(array $permissions): array
    {
        $filtered = [];
        foreach ($permissions as $perm) {
            if (! is_string($perm) || $perm === '') {
                continue;
            }
            $prefix = explode('.', $perm, 2)[0] ?? '';
            if (in_array($prefix, self::PELICAN_NATIVE_PREFIXES, true)) {
                $filtered[] = $perm;
            }
        }

        return array_values(array_unique($filtered));
    }

    /**
     * List subusers for a server — always live from the Pelican Client API.
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
        // Strip Peregrine-specific + plugin perms before talking to
        // Pelican — see PELICAN_NATIVE_PREFIXES doc.
        $pelicanPermissions = self::filterPelicanNative($permissions);

        $response = $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/users", [
                'email' => $email,
                'permissions' => $pelicanPermissions,
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
        // Strip Peregrine-specific + plugin perms before talking to
        // Pelican — see PELICAN_NATIVE_PREFIXES doc.
        $pelicanPermissions = self::filterPelicanNative($permissions);

        $response = $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/users/{$subuserUuid}", [
                'permissions' => $pelicanPermissions,
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
