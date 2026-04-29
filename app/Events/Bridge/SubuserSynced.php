<?php

namespace App\Events\Bridge;

use App\Enums\PelicanEventKind;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a Pelican subuser change webhook lands. The invitations
 * plugin (plugins/invitations/) registers a listener on this event to
 * persist into its own `invitations_pelican_subusers` table.
 *
 * Core does NOT write to a subusers table — that domain belongs to the
 * invitations plugin entirely. This event is the contract between core
 * and the plugin: core delivers the signal, plugin owns the storage.
 *
 * Plugin queue-safe contract (CLAUDE.md): the event class lives in core
 * (App\Events\Bridge), so it is safely serialisable in the queue. The
 * plugin's listener should be SYNC (not queued) to avoid plugin classes
 * landing in the queue payload.
 */
class SubuserSynced
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly PelicanEventKind $eventKind,
        public readonly int $pelicanSubuserId,
        public readonly int $pelicanServerId,
        public readonly int $pelicanUserId,
        public readonly array $payload,
    ) {}
}
