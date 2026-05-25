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
 * @phpstan-type QueryTarget array{type: ?string, family: string, queryable: bool}
 */
class EggGameTypeResolver
{
    private const NS = PlayerCounterServiceProvider::PLUGIN_ID;

    /**
     * @return QueryTarget
     */
    public function resolve(?Egg $egg): array
    {
        $unknown = ['type' => null, 'family' => 'unknown', 'queryable' => false];

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

        $fallback = config(self::NS.'.fallback_type');
        if (is_string($fallback) && $fallback !== '') {
            return ['type' => $fallback, 'family' => 'other', 'queryable' => true];
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
        ];
    }
}
