<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Concerns;

use App\Models\Server;
use Illuminate\Http\Request;

/**
 * Server lookup + permission gating shared by the plugin's server-facing
 * controllers. Resolves by numeric server id — the React shell routes by
 * `server.id` and passes it to `registerServerHomeSection({ serverId })` — then
 * uses `$server->identifier` for the actual Pelican calls. Scoped to servers
 * the caller can access. Admins and owners bypass the granular check; subusers
 * need the Easy Configuration permission, falling back to the matching core
 * file permission when Invitations isn't managing ours.
 */
trait ResolvesServerAccess
{
    protected function resolveServer(string $id, Request $request): Server
    {
        return Server::query()
            ->accessibleBy($request->user())
            ->findOrFail((int) $id);
    }

    protected function authorizeServer(Request $request, Server $server, string $permission, ?string $fallback = null): void
    {
        $user = $request->user();

        if ($user !== null && $user->is_admin) {
            return;
        }
        if ($user !== null && $user->hasServerPermission($server, $permission)) {
            return;
        }
        if ($user !== null && $fallback !== null && $user->hasServerPermission($server, $fallback)) {
            return;
        }

        abort(403);
    }
}
