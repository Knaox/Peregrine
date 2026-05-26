<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Services;

use App\Models\Egg;
use Plugins\PeregrinePlayerCounter\PlayerCounterServiceProvider;

/**
 * Maps a Pelican Egg to a GameDig query target by matching configured
 * substrings against the egg's name, docker image and tags. Resolution order
 * (first match wins): curated `overrides` → the generated GameDig `games`
 * catalogue → the generic `fallback_type`. Pure and side-effect free, so it's
 * trivially unit-testable.
 *
 * @phpstan-type PortStrategy array{mode: string, value?: int, env?: string, applied_by_gamedig?: bool}
 * @phpstan-type ConsolePatterns array{count: string, name: ?string, flags: string}
 * @phpstan-type QueryTarget array{type: ?string, family: string, queryable: bool, query_port: PortStrategy, console: ?ConsolePatterns}
 */
class EggGameTypeResolver
{
    private const NS = PlayerCounterServiceProvider::PLUGIN_ID;

    /**
     * @return QueryTarget
     */
    public function resolve(?Egg $egg): array
    {
        if (! $egg) {
            return ['type' => null, 'family' => 'unknown', 'queryable' => false, 'query_port' => ['mode' => 'same'], 'console' => null];
        }

        $haystack = $this->haystack($egg);
        $target = $this->matchType($haystack);

        // Console-count fallback patterns (crossplay games with no wire query),
        // attached independently of the A2S/RCON type — used only if that fails.
        $target['console'] = $this->consoleFor($haystack);

        return $target;
    }

    /**
     * @return array{type: ?string, family: string, queryable: bool, query_port: PortStrategy}
     */
    private function matchType(string $haystack): array
    {
        // 1. Curated overrides, then 2. the generated catalogue. Both share the
        // same rule shape and "first substring match wins" semantics.
        foreach ([self::NS.'.overrides', self::NS.'.games'] as $bucket) {
            foreach ((array) config($bucket, []) as $rule) {
                foreach ((array) ($rule['match'] ?? []) as $needle) {
                    $needle = strtolower((string) $needle);
                    if ($needle !== '' && str_contains($haystack, $needle)) {
                        return $this->normalize($rule);
                    }
                }
            }
        }

        // 3. No dedicated rule: fall back to a generic A2S probe so the card
        // still shows and attempts a count on the game port (config
        // `fallback_type`, default 'protocol-valve'). Set it to '' to mark
        // unmatched eggs unqueryable instead.
        $fallback = config(self::NS.'.fallback_type');
        if (is_string($fallback) && $fallback !== '') {
            return ['type' => $fallback, 'family' => 'other', 'queryable' => true, 'query_port' => ['mode' => 'same']];
        }

        return ['type' => null, 'family' => 'unknown', 'queryable' => false, 'query_port' => ['mode' => 'same']];
    }

    /**
     * First `console_count` rule whose any `match` substring is in the haystack.
     *
     * @return ConsolePatterns|null
     */
    private function consoleFor(string $haystack): ?array
    {
        foreach ((array) config(self::NS.'.console_count', []) as $rule) {
            foreach ((array) ($rule['match'] ?? []) as $needle) {
                $needle = strtolower((string) $needle);
                if ($needle === '' || ! str_contains($haystack, $needle)) {
                    continue;
                }
                $count = $rule['count'] ?? null;
                if (is_string($count) && $count !== '') {
                    return [
                        'count' => $count,
                        'name' => is_string($rule['name'] ?? null) ? $rule['name'] : null,
                        'flags' => is_string($rule['flags'] ?? null) ? $rule['flags'] : '',
                    ];
                }
            }
        }

        return null;
    }

    private function haystack(Egg $egg): string
    {
        $tags = is_array($egg->tags) ? implode(' ', $egg->tags) : (string) $egg->tags;

        return strtolower(trim("{$egg->name} {$egg->docker_image} {$tags}"));
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

        $port = $rule['query_port'] ?? ['mode' => 'same'];
        if (! is_array($port) || ! isset($port['mode'])) {
            $port = ['mode' => 'same'];
        }

        return [
            'type' => $type,
            'family' => (string) ($rule['family'] ?? 'other'),
            'queryable' => $queryable,
            'query_port' => $port,
        ];
    }
}
