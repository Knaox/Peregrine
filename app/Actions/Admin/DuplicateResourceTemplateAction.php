<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\ResourceTemplate;

/**
 * Clones a `ResourceTemplate` row, returning the new instance ready for
 * the admin to tweak. All spec attributes are copied verbatim (RAM,
 * CPU, disk, swap, I/O weight, cpu_pinning) — only the `name` changes
 * to satisfy the UNIQUE constraint.
 *
 * Naming strategy : append `-copy`, then `-copy-2`, `-copy-3`, … until
 * a free slot is found. Same logic as `DuplicateServerConfigurationAction`
 * for behavioural consistency on the admin side.
 *
 * Configurations bound to the source are NOT re-bound to the clone.
 * That's intentional : the typical use case is "snapshot a template
 * before tweaking it", and silently re-pointing every config at the
 * fresh row would defeat the purpose. Admins re-bind manually if
 * needed.
 */
final class DuplicateResourceTemplateAction
{
    public function __invoke(ResourceTemplate $source): ResourceTemplate
    {
        $clone = $source->replicate();
        $clone->name = $this->resolveUniqueName($source->name);
        $clone->save();

        return $clone->refresh();
    }

    /**
     * Strip an existing `-copy[-N]` suffix from the source so a clone of
     * a clone produces `Medium-copy` rather than `Medium-copy-copy`.
     */
    private function resolveUniqueName(string $base): string
    {
        $stem = preg_replace('/-copy(?:-\d+)?$/i', '', $base) ?? $base;
        if ($stem === '') {
            $stem = $base;
        }

        $candidate = $stem.'-copy';
        $i = 2;
        while ($this->nameExists($candidate)) {
            $candidate = $stem.'-copy-'.$i;
            $i++;
            if ($i > 999) {
                return $stem.'-copy-'.now()->timestamp;
            }
        }

        return $candidate;
    }

    private function nameExists(string $candidate): bool
    {
        return ResourceTemplate::query()->where('name', $candidate)->exists();
    }
}
