<?php

namespace App\Events;

use App\Models\Server;

/**
 * Fired by `ServerController::reinstall` immediately before the Pelican
 * reinstall request is dispatched. Lets plugins hook in to clean up state
 * tied to the server's previous configuration — e.g. the modpack-installer
 * plugin uses this signal to drop its `modpack_installations` row so the
 * modpack tab stops showing the pack as installed once the server has been
 * wiped/reinstalled.
 *
 * Fully decoupled: the core does not know anything about plugins. Listeners
 * register themselves through the standard Laravel event dispatcher (a
 * plugin's service provider can call `Event::listen(ServerReinstallStarting,
 * MyListener::class)` in its `boot()` method).
 *
 * Listeners must be tolerant — exceptions are caught by the dispatcher
 * (Laravel's default behaviour) but a listener that throws will still log
 * and keep the user-facing request flowing. Don't make the reinstall
 * dependent on a listener succeeding.
 */
class ServerReinstallStarting
{
    public function __construct(
        public readonly Server $server,
        public readonly bool $wipeData,
    ) {}
}
