<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Services;

use App\Models\Egg;
use Plugins\PeregrinePlayerCounter\PlayerCounterServiceProvider;

/**
 * Maps a Pelican Egg to a GameDig query target by matching configured
 * substrings against the egg's name, docker image and tags. Pure and
 * side-effect free, so it's trivially unit-testable.
 *
 * @phpstan-type QueryTarget array{type: ?string, family: string, queryable: bool, query_offset: int}
 */
class EggGameTypeResolver
{
    private const NS = PlayerCounterServiceProvider::PLUGIN_ID;

    /**
     * @return QueryTarget
     */
    public function resolve(?Egg $egg): array
    {
        $unknown = ['type' => null, 'family' => 'unknown', 'queryable' => false, 'query_offset' => 0];

        if (! $egg) {
            return $unknown;
        }

        $haystack = $this->haystack($egg);

        foreach ((array) config(self::NS.'.rules', []) as $rule) {
            foreach ((array) ($rule['match'] ?? []) as $needle) {
                $needle = strtolower((string) $needle);
                if ($needle !== '' && str_contains($haystack, $needle)) {
                    return $this->normalize($rule);
                }
            }
        }

        if ($steam = $this->steamFallback($egg)) {
            return $steam;
        }

        $fallback = config(self::NS.'.fallback_type');
        if (is_string($fallback) && $fallback !== '') {
            return ['type' => $fallback, 'family' => 'other', 'queryable' => true, 'query_offset' => 0];
        }

        return $unknown;
    }

    /**
     * Generic Steam/A2S detection: an egg that installs via SteamCMD or ships a
     * Source dedicated-server binary is queryable with GameDig's generic
     * 'protocol-valve' even without a dedicated rule — this is what lets "any
     * Steam server" report a count. Matches a wider haystack that also covers
     * the startup command (where SteamCMD's `app_update <id>` lives). Runs only
     * after the explicit rules, so a mapped game (e.g. an EOS title that also
     * installs through SteamCMD) keeps its own handling.
     *
     * @return QueryTarget|null
     */
    private function steamFallback(Egg $egg): ?array
    {
        $cfg = (array) config(self::NS.'.steam_fallback', []);
        $type = $cfg['type'] ?? null;

        if (($cfg['enabled'] ?? false) !== true || ! is_string($type) || $type === '') {
            return null;
        }

        $haystack = $this->haystack($egg, withStartup: true);

        foreach ((array) ($cfg['match'] ?? []) as $needle) {
            $needle = strtolower((string) $needle);
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return ['type' => $type, 'family' => (string) ($cfg['family'] ?? 'source'), 'queryable' => true, 'query_offset' => 0];
            }
        }

        return null;
    }

    private function haystack(Egg $egg, bool $withStartup = false): string
    {
        $tags = is_array($egg->tags) ? implode(' ', $egg->tags) : (string) $egg->tags;
        $extra = $withStartup ? " {$egg->startup} {$egg->description}" : '';

        return strtolower(trim("{$egg->name} {$egg->docker_image} {$tags}{$extra}"));
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return QueryTarget
     */
    private function normalize(array $rule): array
    {
        $type = $rule['type'] ?? null;
        $type = is_string($type) && $type !== '' ? $type : null;
        $queryable = ($rule['queryable'] ?? true) === true && $type !== null;

        return [
            'type' => $type,
            'family' => (string) ($rule['family'] ?? 'other'),
            'queryable' => $queryable,
            'query_offset' => (int) ($rule['query_offset'] ?? 0),
        ];
    }
}
