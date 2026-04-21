<?php

namespace App\Support;

/**
 * Pure color conversion helpers — no state, no dependencies. Extracted from
 * ThemeService so the math is individually testable and reusable by anyone
 * needing hex → rgba / rgb-triplet conversions (custom components, plugins,
 * email templates, etc.).
 */
final class ColorUtils
{
    /**
     * Convert a hex color ("#f97316" or "f97316") to an RGB triplet string
     * ready for CSS rgba() usage — e.g. "249, 115, 22".
     *
     * Short-form ("#abc") and raw rgb() strings are accepted. Invalid input
     * returns "0, 0, 0" rather than throwing — the caller is rendering CSS
     * where a silent fallback is safer than a crash.
     */
    public static function hexToRgbTriplet(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (str_starts_with($hex, 'rgb')) {
            return $hex;
        }
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6) {
            return '0, 0, 0';
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "{$r}, {$g}, {$b}";
    }

    /**
     * Convert a hex color to an rgba() string with the given alpha (0.0–1.0).
     * Same tolerance rules as hexToRgbTriplet().
     */
    public static function hexToRgba(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');

        if (str_starts_with($hex, 'rgb')) {
            return $hex;
        }

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6) {
            return "rgba(0, 0, 0, {$alpha})";
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }
}
