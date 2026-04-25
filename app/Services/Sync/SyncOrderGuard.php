<?php

namespace App\Services\Sync;

use App\Models\Egg;
use App\Models\Node;
use App\Models\User;

/**
 * Enforces the required Pelican-sync ordering in the admin UI.
 *
 *   1. Nodes
 *   2. Users
 *   3. Eggs
 *   4. Servers
 *
 * Why: a Server row references egg_id / node_id / user_id as LOCAL FKs.
 * If an admin syncs servers before the upstream rows exist, every server
 * either fails to insert or imports with broken FKs that surface later
 * as silent provisioning bugs (the prod incident around node 21 was
 * a sibling symptom of the same family).
 *
 * "Step done" is detected by the presence of at least one matching row
 * locally — sync_logs isn't a reliable signal because the Filament UI
 * doesn't write to it (only the CLI commands do).
 */
class SyncOrderGuard
{
    public const ORDER_HINT_FR = 'Ordre obligatoire : 1. Nodes → 2. Users → 3. Eggs → 4. Servers. Chaque étape dépend de la précédente.';

    public function nodesSynced(): bool
    {
        return Node::query()->exists();
    }

    public function usersSynced(): bool
    {
        return User::query()->whereNotNull('pelican_user_id')->exists();
    }

    public function eggsSynced(): bool
    {
        return Egg::query()->exists();
    }

    /**
     * Returns null if the action is allowed, otherwise a short reason
     * string suitable for an action's `disabled()` tooltip.
     */
    public function blockSyncUsers(): ?string
    {
        if (! $this->nodesSynced()) {
            return 'Synchronisez d\'abord les Nodes (étape 1/4).';
        }
        return null;
    }

    public function blockSyncEggs(): ?string
    {
        if (! $this->nodesSynced()) {
            return 'Synchronisez d\'abord les Nodes (étape 1/4).';
        }
        if (! $this->usersSynced()) {
            return 'Synchronisez d\'abord les Users (étape 2/4).';
        }
        return null;
    }

    public function blockSyncServers(): ?string
    {
        if (! $this->nodesSynced()) {
            return 'Synchronisez d\'abord les Nodes (étape 1/4).';
        }
        if (! $this->usersSynced()) {
            return 'Synchronisez d\'abord les Users (étape 2/4).';
        }
        if (! $this->eggsSynced()) {
            return 'Synchronisez d\'abord les Eggs (étape 3/4).';
        }
        return null;
    }

    /**
     * Human-readable subheading for each List page, with a ✅/⚠️ marker
     * per step so the admin sees at a glance where they are in the chain.
     */
    public function statusLine(): string
    {
        $tag = fn (bool $done, string $label): string =>
            ($done ? '✅' : '⚠️').' '.$label;

        return implode(' → ', [
            $tag($this->nodesSynced(), '1. Nodes'),
            $tag($this->usersSynced(), '2. Users'),
            $tag($this->eggsSynced(), '3. Eggs'),
            '4. Servers',
        ]);
    }
}
