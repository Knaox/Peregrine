<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\ServerConfiguration;

/**
 * Clones a `ServerConfiguration` row, returning the new instance ready for
 * the admin to tweak in the edit form. All technical attributes are
 * copied verbatim (RAM, CPU, disk, egg, node, env_var_mapping, allowed
 * nodes, feature limits, etc.) — only the `internal_name` is changed
 * to avoid collisions.
 *
 * Naming strategy : append `-copy`, then `-copy-2`, `-copy-3`, … until a
 * free slot is found. Sticky and predictable for the admin (no random
 * suffix to read aloud).
 *
 * Pivots intentionally NOT copied :
 *  - `shop_server_configuration` : a fresh clone is invisible to all
 *    shops by default ; the admin re-authorizes (and toggles visibility)
 *    consciously per-shop. Mirrors the manual pattern used everywhere
 *    else in the catalog management flow.
 *
 * Servers : the FK on `servers.server_configuration_id` is left
 * untouched on the source — already-provisioned servers keep referencing
 * the original configuration. The clone has zero attached servers.
 */
final class DuplicateServerConfigurationAction
{
    public function __invoke(ServerConfiguration $source): ServerConfiguration
    {
        $clone = $source->replicate();
        $clone->internal_name = $this->resolveUniqueInternalName($source->internal_name);
        $clone->save();

        return $clone->refresh();
    }

    /**
     * Returns an internal_name that does not yet exist in the table.
     * Skips the `-copy` suffix when the source already has one (avoids
     * `mc-2gb-copy-copy`) and increments the trailing counter instead.
     */
    private function resolveUniqueInternalName(string $base): string
    {
        // Strip an existing `-copy` or `-copy-N` suffix from the source
        // so the generated chain stays compact.
        $stem = preg_replace('/-copy(?:-\d+)?$/i', '', $base) ?? $base;
        if ($stem === '') {
            $stem = $base;
        }

        $candidate = $stem.'-copy';
        $i = 2;
        while ($this->internalNameExists($candidate)) {
            $candidate = $stem.'-copy-'.$i;
            $i++;

            if ($i > 999) {
                // Safety net : if 999 copies already exist the admin has
                // bigger problems. Fall back to a timestamped suffix
                // rather than looping forever.
                return $stem.'-copy-'.now()->timestamp;
            }
        }

        return $candidate;
    }

    private function internalNameExists(string $candidate): bool
    {
        return ServerConfiguration::query()
            ->where('internal_name', $candidate)
            ->exists();
    }
}
