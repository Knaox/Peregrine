<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Boost;

/**
 * Pure boost math. In MULTIPLY mode the boosted value is `baseline * multiplier`,
 * capped to the lowest of the user's per-parameter max_cap and the template's
 * config.max (whichever are defined). In DIVIDE mode (per-parameter "deboost":
 * shrinks a value/interval) it is `baseline / multiplier`, floored at the
 * template's config.min (or 0) so it never drops below a playable minimum — the
 * caps don't apply since the value only goes down. Formatting matches the
 * parameter's numeric type so the value written back stays valid.
 */
final class BoostCalculator
{
    public function compute(float $baseline, float $multiplier, ?float $maxCap, ?float $templateMax, bool $invert = false, ?float $floor = null): float
    {
        if ($invert) {
            $value = $multiplier != 0.0 ? $baseline / $multiplier : $baseline;

            return max($value, $floor ?? 0.0);
        }

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
