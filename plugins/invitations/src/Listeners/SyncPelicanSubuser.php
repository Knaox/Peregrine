<?php

namespace Plugins\Invitations\Listeners;

use App\Enums\PelicanEventKind;
use App\Events\Bridge\SubuserSynced;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Plugins\Invitations\Models\PelicanSubuser;

/**
 * Persists a Pelican subuser change locally inside the invitations
 * plugin's mirror table. Listens to the core `App\Events\Bridge\SubuserSynced`
 * event fired by `App\Jobs\Bridge\DispatchSubuserSyncedJob` whenever
 * Pelican forwards a subuser webhook.
 *
 * Plugin queue-safe contract (CLAUDE.md): this listener is SYNC — it
 * runs in-process when the core job fires the event, so no plugin
 * class is ever serialised into the queue payload.
 *
 * Final to follow the plugin queue-safe contract.
 */
final class SyncPelicanSubuser
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(SubuserSynced $event): void
    {
        if ($event->eventKind === PelicanEventKind::SubuserRemoved) {
            $this->handleRemoval($event);
            return;
        }

        $payload = $event->payload;

        $permissions = $payload['permissions'] ?? null;
        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            $permissions = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($permissions)) {
            $permissions = [];
        }

        // Match on (pelican_server_id, pelican_user_id) — the natural pair
        // both sources can produce. A backfilled row (no subuser_id yet)
        // will be filled in here when the next webhook arrives, instead of
        // being duplicated.
        //
        // Webhook payloads expose the subuser as a numeric record (id,
        // server_id, user_id, permissions) — the human-readable fields
        // (email, uuid, username) live on the related User. We resolve
        // them locally so the mirror payload matches the API live shape
        // expected by the React sub-users page.
        $localUser = User::where('pelican_user_id', $event->pelicanUserId)->first();

        $values = [
            'pelican_subuser_id' => $event->pelicanSubuserId,
            'email' => $localUser?->email,
            'username' => $localUser?->name,
            'permissions' => $permissions,
            'pelican_created_at' => $this->parseDate($payload['created_at'] ?? null),
            'pelican_updated_at' => $this->parseDate($payload['updated_at'] ?? null),
        ];

        // The Pelican subuser UUID isn't part of the eloquent webhook
        // payload — it lives on the related User. Only overwrite the
        // mirror's `uuid` when Pelican actually shipped one (rare, but
        // possible on `eloquent.created: Subuser` from some Pelican
        // builds). Otherwise we keep whatever the backfiller stored.
        if (! empty($payload['uuid'])) {
            $values['uuid'] = (string) $payload['uuid'];
        }

        PelicanSubuser::updateOrCreate(
            [
                'pelican_server_id' => $event->pelicanServerId,
                'pelican_user_id' => $event->pelicanUserId,
            ],
            $values,
        );

        Log::info('SyncPelicanSubuser: subuser mirrored in invitations plugin', [
            'pelican_subuser_id' => $event->pelicanSubuserId,
            'pelican_server_id' => $event->pelicanServerId,
            'event_kind' => $event->eventKind->value,
        ]);
    }

    private function handleRemoval(SubuserSynced $event): void
    {
        // Match on the (server, user) pair so a backfill-only row (which
        // has no `pelican_subuser_id`) is still removable.
        PelicanSubuser::where('pelican_server_id', $event->pelicanServerId)
            ->where('pelican_user_id', $event->pelicanUserId)
            ->delete();

        Log::info('SyncPelicanSubuser: subuser removed from invitations plugin mirror', [
            'pelican_subuser_id' => $event->pelicanSubuserId,
            'pelican_server_id' => $event->pelicanServerId,
            'pelican_user_id' => $event->pelicanUserId,
        ]);
    }

    private function parseDate(mixed $raw): ?Carbon
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
