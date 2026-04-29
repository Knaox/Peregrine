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
    /**
     * Kept for any caller that still references the constant directly. New
     * code should use `orderHint()` so the locale follows the request.
     */
    public const ORDER_HINT = 'Required order: 1. Nodes → 2. Users → 3. Eggs → 4. Servers. Each step depends on the previous one.';

    public static function orderHint(): string
    {
        return __('admin.sync_order.order_hint');
    }

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
            return __('admin.sync_order.block_nodes_first');
        }
        return null;
    }

    public function blockSyncEggs(): ?string
    {
        if (! $this->nodesSynced()) {
            return __('admin.sync_order.block_nodes_first');
        }
        if (! $this->usersSynced()) {
            return __('admin.sync_order.block_users_first');
        }
        return null;
    }

    public function blockSyncServers(): ?string
    {
        if (! $this->nodesSynced()) {
            return __('admin.sync_order.block_nodes_first');
        }
        if (! $this->usersSynced()) {
            return __('admin.sync_order.block_users_first');
        }
        if (! $this->eggsSynced()) {
            return __('admin.sync_order.block_eggs_first');
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
            $tag($this->nodesSynced(), __('admin.sync_order.step_nodes')),
            $tag($this->usersSynced(), __('admin.sync_order.step_users')),
            $tag($this->eggsSynced(), __('admin.sync_order.step_eggs')),
            __('admin.sync_order.step_servers'),
        ]);
    }
}
