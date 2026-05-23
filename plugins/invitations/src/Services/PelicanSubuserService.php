<?php

namespace Plugins\Invitations\Services;

use App\Services\Pelican\Concerns\MakesClientRequests;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

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
     * @param  array<int, string>  $permissions
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
     * @param  array<int, string>  $permissions
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
     * @param  array<int, string>  $permissions
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

    /**
     * Idempotently make sure `$email` is a subuser of the server with exactly
     * `$permissions`. Creates the subuser, or — if Pelican reports the email is
     * already a subuser (re-invitation, or a leftover from a previous grant) —
     * updates their permissions instead.
     *
     * Why this matters: the previous flow listed-then-create/updated, and a
     * blind create on a duplicate 422'd. Since the caller wrapped this in a DB
     * transaction, that exception rolled back `accepted_at` + the local pivot —
     * the invite got stuck "pending" with stale permissions. Here we try create
     * first (one call for the common first-invite case, throttle-friendly) and
     * fall back to an update on the duplicate error, so an accept never gets
     * stuck. A genuine error (bad permissions, Pelican down) still propagates so
     * the caller can surface it and NOT mark the invite accepted.
     *
     * @param  array<int, string>  $permissions
     */
    public function syncSubuser(string $serverIdentifier, string $email, array $permissions): void
    {
        try {
            $this->createSubuser($serverIdentifier, $email, $permissions);

            return;
        } catch (RequestException $e) {
            // Anything other than "already a subuser" is a real failure.
            if (! $this->isAlreadyAssignedError($e)) {
                throw $e;
            }
        }

        // Already a subuser → resolve their uuid and update the permissions.
        $uuid = $this->findSubuserUuidByEmail($serverIdentifier, $email);
        if ($uuid !== null) {
            $this->updateSubuser($serverIdentifier, $uuid, $permissions);

            return;
        }

        // Pelican says the email is already a subuser but we could not match it
        // back (the list response omitted the email, pagination, or a race).
        // The subuser already exists on Pelican, so we deliberately do NOT throw
        // — the local grant must still land. Permissions on the Pelican side may
        // be momentarily stale; logged for visibility.
        Log::warning('invitations: email already a subuser on Pelican but could not be matched for update', [
            'server' => $serverIdentifier,
            'email' => $email,
        ]);
    }

    /**
     * Find a subuser's uuid by email (case-insensitive). Returns null when the
     * server has no matching subuser, or when the list response carries no email
     * to match against.
     */
    public function findSubuserUuidByEmail(string $serverIdentifier, string $email): ?string
    {
        $needle = strtolower(trim($email));

        foreach ($this->listSubusers($serverIdentifier) as $sub) {
            $subEmail = isset($sub['email']) ? strtolower(trim((string) $sub['email'])) : null;
            if ($subEmail !== null && $subEmail === $needle && ! empty($sub['uuid'])) {
                return (string) $sub['uuid'];
            }
        }

        return null;
    }

    /**
     * Whether a Pelican client-API error means "this email is already a subuser
     * of the server" — the only failure we recover from in syncSubuser. Pelican
     * surfaces it as a 400/422 whose body mentions "already" / "exists".
     */
    private function isAlreadyAssignedError(RequestException $e): bool
    {
        $response = $e->response;
        if ($response === null) {
            return false;
        }

        if (! in_array($response->status(), [400, 422], true)) {
            return false;
        }

        $body = strtolower((string) $response->body());

        return str_contains($body, 'already')
            || str_contains($body, 'exists')
            || str_contains($body, 'duplicate');
    }
}
