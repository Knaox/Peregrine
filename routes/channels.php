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

/*
 * User-scoped private channel : `private-user.{userId}`.
 *
 * Subscribed by the React DashboardPage (/servers) to receive every mirror
 * change touching a server the user has access to (owner or subuser pivot).
 * The producer side fans out by adding one PrivateChannel('user.X') per
 * access user, so every user sees only their own slice of events.
 *
 * Authorization : the requesting user must BE the owner of the channel
 * (matching id) OR an admin. Admins authorize for any user channel as a
 * convenience for support tooling, even though they normally listen on
 * `private-admin-mirror` instead.
 */
Broadcast::channel('user.{userId}', function (User $user, int $userId): bool {
    return $user->id === $userId || (bool) $user->is_admin;
});

/*
 * Admin-scoped private channel : `private-admin-mirror`.
 *
 * Subscribed by /admin/servers — admins need to see every server-state
 * transition regardless of ownership, so the producer broadcasts every
 * ServerMirrorChanged on this single channel and admins all listen here.
 * Implicit deny for non-admins.
 */
Broadcast::channel('admin-mirror', function (User $user): bool {
    return (bool) $user->is_admin;
});
