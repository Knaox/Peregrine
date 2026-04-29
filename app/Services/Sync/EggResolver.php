<?php

namespace App\Services\Sync;

use App\Models\Egg;
use Illuminate\Support\Facades\Log;

/**
 * Resolves a Pelican egg id (as shipped in webhook payloads) to the local
 * `eggs.id` Peregrine has mirrored. Auto-triggers a one-shot egg sync if
 * the local row is missing — covers the case where a server webhook
 * arrives before the egg sync has caught up.
 *
 * Extracted from SyncServerFromPelicanWebhookJob.
 */
final class EggResolver
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function resolveLocalEggId(array $data, int $pelicanServerId): ?int
    {
        $pelicanEggId = $data['egg_id'] ?? $data['egg'] ?? null;
        if ($pelicanEggId === null || $pelicanEggId === '') {
            return null;
        }

        $pelicanEggId = (int) $pelicanEggId;
        $localEggId = Egg::where('pelican_egg_id', $pelicanEggId)->value('id');

        if ($localEggId === null) {
            try {
                app(InfrastructureSync::class)->syncEggs();
                $localEggId = Egg::where('pelican_egg_id', $pelicanEggId)->value('id');
            } catch (\Throwable $e) {
                Log::warning('EggResolver: egg auto-sync failed', [
                    'pelican_server_id' => $pelicanServerId,
                    'pelican_egg_id' => $pelicanEggId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($localEggId === null) {
            Log::info('EggResolver: egg not resolvable locally', [
                'pelican_server_id' => $pelicanServerId,
                'pelican_egg_id' => $pelicanEggId,
            ]);
        }

        return $localEggId !== null ? (int) $localEggId : null;
    }
}
