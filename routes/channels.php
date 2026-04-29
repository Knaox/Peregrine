<?php

use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * Server-scoped private channel : `private-server.{localServerId}`.
 *
 * Subscribed by every page that wants live updates on a server's mirror
 * (`/server/{x}/network`, `/databases`, `/backups`, `/sub-users`). The
 * payload of `mirror.changed` events carries `{resource, action,
 * resource_id}` — the React hook then invalidates the matching
 * TanStack Query key so the UI refetches silently.
 *
 * Authorization mirrors the API access rule used by the existing
 * controllers : the user must be an admin OR have a row in the
 * `server_user` pivot for the requested server. Unknown server id =
 * implicit deny. Banned users (no `is_admin`, no pivot) implicit deny.
 */
Broadcast::channel('server.{serverId}', function (User $user, int $serverId): bool {
    $server = Server::find($serverId);

    if ($server === null) {
        return false;
    }

    if ($user->is_admin) {
        return true;
    }

    return $server->accessUsers()->where('users.id', $user->id)->exists();
});
