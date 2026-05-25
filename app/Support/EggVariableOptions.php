<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Egg;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Builds the option list of an egg's environment variables for Filament
 * selects (the `env_var_mapping` repeater + the IP-variable picker on
 * ServerConfigurationResource).
 *
 * Egg variables are NOT mirrored locally — they're fetched live from Pelican
 * (PelicanApplicationService::getEggVariables) and cached briefly so a live
 * form doesn't hammer the API on every Livewire re-render. Pelican unreachable
 * → empty list (the form degrades to a helper text instead of crashing), and
 * the failure is NOT cached so the next render retries.
 *
 * Keys are the `env_variable` names actually stored in `env_var_mapping` ;
 * labels add the human name for clarity, e.g. "SERVER_PORT — Server Port".
 */
final class EggVariableOptions
{
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * @param  int|string|null  $localEggId  The LOCAL `eggs.id` (the Filament form value), NOT the Pelican id.
     * @return array<string, string>
     */
    public static function forEgg(int|string|null $localEggId): array
    {
        $localEggId = (int) $localEggId;
        if ($localEggId <= 0) {
            return [];
        }

        $egg = Egg::find($localEggId);
        if ($egg === null || $egg->pelican_egg_id === null) {
            return [];
        }

        $pelicanEggId = (int) $egg->pelican_egg_id;
        $cacheKey = "egg:{$pelicanEggId}:variables";

        /** @var list<array{env_variable: string, name: string, default: string}>|null $variables */
        $variables = Cache::get($cacheKey);
        if (! is_array($variables)) {
            try {
                $variables = app(PelicanApplicationService::class)->getEggVariables($pelicanEggId);
                Cache::put($cacheKey, $variables, self::CACHE_TTL);
            } catch (\Throwable $e) {
                Log::warning('EggVariableOptions: failed to fetch egg variables', [
                    'pelican_egg_id' => $pelicanEggId,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        }

        $options = [];
        foreach ($variables as $variable) {
            if (! is_array($variable)) {
                continue;
            }
            $key = trim((string) ($variable['env_variable'] ?? ''));
            if ($key === '') {
                continue;
            }
            $name = trim((string) ($variable['name'] ?? ''));
            $options[$key] = ($name !== '' && $name !== $key) ? "{$key} — {$name}" : $key;
        }

        return $options;
    }
}
