<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Boost;

/**
 * Pure boost math: the boosted value is `baseline * multiplier`, capped to the
 * lowest of the user's per-parameter max_cap and the template's config.max
 * (whichever are defined). Formatting matches the parameter's numeric type so
 * the value written back stays valid (integers stay integers).
 */
final class BoostCalculator
{
    public function compute(float $baseline, float $multiplier, ?float $maxCap, ?float $templateMax): float
    {
        $value = $baseline * $multiplier;

        foreach ([$maxCap, $templateMax] as $cap) {
            if ($cap !== null) {
                $value = min($value, $cap);
            }
        }

        return $value;
    }

    public function format(float $value, bool $float): string
    {
        if (! $float) {
            return (string) (int) round($value);
        }

        $formatted = rtrim(rtrim(sprintf('%.6f', $value), '0'), '.');

        return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
    }
}
