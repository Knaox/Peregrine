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

        // No dedicated rule: fall back to a generic A2S probe so the card still
        // shows and attempts a count (config `fallback_type`, default
        // 'protocol-valve'). Whether it actually returns anything is up to the
        // admin who whitelisted the egg. Set it to '' to mark unmapped games
        // unqueryable instead.
        $fallback = config(self::NS.'.fallback_type');
        if (is_string($fallback) && $fallback !== '') {
            return ['type' => $fallback, 'family' => 'other', 'queryable' => true, 'query_offset' => 0];
        }

        return $unknown;
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

        return [
            'type' => $type,
            'family' => (string) ($rule['family'] ?? 'other'),
            'queryable' => $queryable,
            'query_offset' => (int) ($rule['query_offset'] ?? 0),
        ];
    }
}
