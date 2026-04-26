<?php

namespace Plugins\EggConfigEditor\Services;

use RuntimeException;

/**
 * Parse / serialize the 3 config formats supported in v0.1.
 *
 * The contract is symmetric : `parse()` returns a flat associative array of
 * scalar values keyed by the same string the player will see in their form,
 * `serialize()` rebuilds the file content from that same flat array. Round
 * trips are stable for the formats we support — comments and unknown lines
 * in `.properties` / `.ini` are preserved verbatim, only matching keys are
 * rewritten.
 *
 * Format-specific notes :
 *   - `properties` : standard Java-style `key=value` per line. Comments
 *     start with `#` or `!` and are kept untouched.
 *   - `ini`        : sections supported via `Section.Key` notation in the
 *     output array. Comments (`;` and `#`) are preserved.
 *   - `json`       : top-level object only (no nested objects). Anything
 *     deeper than 1 level is flattened to a `dot.path.key` notation but the
 *     UI doesn't try to be clever — admins can either use it or not.
 *
 * Why not yaml/toml/xml in v0.1 : the 3 formats above cover Minecraft / ARK
 * / Palworld and ~80% of common game egg use cases. We add others when
 * someone hits "I need yaml for X" — adding parsers blindly bloats the
 * surface area for vulnerabilities.
 */
class ConfigParserService
{
    public const SUPPORTED_TYPES = ['properties', 'ini', 'json'];

    /**
     * Section/key separator used in the FLAT array the parser emits for INI
     * files. ASCII 0x1F (Unit Separator) is invisible and never appears in
     * real INI section or key names — letting us safely round-trip section
     * names that themselves contain dots, like Unreal Engine's
     * `[/script/shootergame.shootergamemode]`.
     */
    public const SECTION_KEY_SEPARATOR = "\x1F";

    /**
     * @return array<string, scalar|null>
     */
    public function parse(string $content, string $type): array
    {
        // Strip the UTF-8 BOM if the file was saved with one (some Windows
        // editors add it). Without this the first character would be the
        // invisible BOM byte and our `[0] === ';'` comment check would miss,
        // leaking lines like `;METADATA=(Diff=true, UseCommands=true)` into
        // the parameter list.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;

        return match ($type) {
            'properties' => $this->parseProperties($content),
            'ini' => $this->parseIni($content),
            'json' => $this->parseJson($content),
            default => throw new RuntimeException("Unsupported config type: {$type}"),
        };
    }

    /**
     * Rebuild the file content from a flat key→value array.
     *
     * For formats that support comments, `$original` is used as the carrier :
     * lines that don't match a known key are preserved as-is. Pass `null` if
     * you don't have the original content (e.g. brand-new file).
     *
     * @param  array<string, scalar|null>  $values
     */
    public function serialize(array $values, string $type, ?string $original = null): string
    {
        return match ($type) {
            'properties' => $this->serializeProperties($values, $original ?? ''),
            'ini' => $this->serializeIni($values, $original ?? ''),
            'json' => $this->serializeJson($values),
            default => throw new RuntimeException("Unsupported config type: {$type}"),
        };
    }

    // -- properties ------------------------------------------------------

    /**
     * @return array<string, string>
     */
    private function parseProperties(string $content): array
    {
        $out = [];
        foreach (preg_split('/\R/u', $content) ?: [] as $line) {
            $trim = $this->stripBom(ltrim($line));
            if ($trim === '') {
                continue;
            }
            // Java .properties comment markers are `#` and `!`.
            if (str_starts_with($trim, '#') || str_starts_with($trim, '!')) {
                continue;
            }
            $eq = strpos($trim, '=');
            if ($eq === false) {
                continue;
            }
            $key = rtrim(substr($trim, 0, $eq));
            // Defensive : drop malformed keys that contain comment markers.
            if ($key === '' || str_contains($key, '#') || str_contains($key, '!')) {
                continue;
            }
            $value = ltrim(substr($trim, $eq + 1));
            $out[$key] = $value;
        }
        return $out;
    }

    /**
     * @param  array<string, scalar|null>  $values
     */
    private function serializeProperties(array $values, string $original): string
    {
        $remaining = $values;
        $lines = [];

        foreach (preg_split('/\R/u', $original) ?: [] as $line) {
            $trim = $this->stripBom(ltrim($line));
            if ($trim === '' || str_starts_with($trim, '#') || str_starts_with($trim, '!')) {
                $lines[] = $line;
                continue;
            }
            $eq = strpos($trim, '=');
            if ($eq === false) {
                $lines[] = $line;
                continue;
            }
            $key = rtrim(substr($trim, 0, $eq));
            if (array_key_exists($key, $remaining)) {
                $lines[] = $key . '=' . $this->scalarToString($remaining[$key]);
                unset($remaining[$key]);
            } else {
                $lines[] = $line;
            }
        }

        // Append keys that didn't exist in the original (admin added a rule
        // for a parameter the file didn't have yet — common after a game
        // update introduces new options).
        foreach ($remaining as $key => $value) {
            $lines[] = $key . '=' . $this->scalarToString($value);
        }

        return implode("\n", $lines);
    }

    // -- ini -------------------------------------------------------------

    /**
     * @return array<string, string>
     */
    private function parseIni(string $content): array
    {
        $out = [];
        $section = '';
        foreach (preg_split('/\R/u', $content) ?: [] as $line) {
            $trim = $this->stripBom(trim($line));
            if ($trim === '') {
                continue;
            }
            // Comment markers : `;` and `#` at the start of a line. Use
            // str_starts_with so we're robust against any oddity that
            // would shift the leading char.
            if (str_starts_with($trim, ';') || str_starts_with($trim, '#')) {
                continue;
            }
            if (str_starts_with($trim, '[') && str_ends_with($trim, ']')) {
                $section = trim(substr($trim, 1, -1));
                continue;
            }
            $eq = strpos($trim, '=');
            if ($eq === false) {
                continue;
            }
            $key = rtrim(substr($trim, 0, $eq));
            // Defensive : if a key somehow contains a `;` or `#` (= comment
            // marker bleeding into the key portion), drop the line — it's
            // malformed and would otherwise show up as a junk parameter.
            if ($key === '' || str_contains($key, ';') || str_contains($key, '#') || str_contains($key, '[')) {
                continue;
            }
            $value = ltrim(substr($trim, $eq + 1));
            $fullKey = $section !== '' ? $section . self::SECTION_KEY_SEPARATOR . $key : $key;
            $out[$fullKey] = $value;
        }
        return $out;
    }

    /**
     * Strip a UTF-8 BOM if present at the start of a string. The BOM is the
     * 3-byte sequence `\xEF\xBB\xBF` — invisible in editors but breaks
     * leading-character checks (`$str[0] === ';'`, etc.).
     */
    private function stripBom(string $s): string
    {
        if (str_starts_with($s, "\xEF\xBB\xBF")) {
            return substr($s, 3);
        }
        return $s;
    }

    /**
     * @param  array<string, scalar|null>  $values
     */
    private function serializeIni(array $values, string $original): string
    {
        $remaining = $values;
        $lines = [];
        $currentSection = '';

        foreach (preg_split('/\R/u', $original) ?: [] as $line) {
            $trim = $this->stripBom(trim($line));
            if ($trim === '' || str_starts_with($trim, ';') || str_starts_with($trim, '#')) {
                $lines[] = $line;
                continue;
            }
            if (str_starts_with($trim, '[') && str_ends_with($trim, ']')) {
                $currentSection = trim(substr($trim, 1, -1));
                $lines[] = $line;
                continue;
            }
            $eq = strpos($trim, '=');
            if ($eq === false) {
                $lines[] = $line;
                continue;
            }
            $key = rtrim(substr($trim, 0, $eq));
            $fullKey = $currentSection !== '' ? $currentSection . self::SECTION_KEY_SEPARATOR . $key : $key;
            if (array_key_exists($fullKey, $remaining)) {
                $indent = substr($line, 0, strspn($line, " \t"));
                $lines[] = $indent . $key . '=' . $this->scalarToString($remaining[$fullKey]);
                unset($remaining[$fullKey]);
            } else {
                $lines[] = $line;
            }
        }

        // Append leftover keys grouped by section. Brand-new sections are
        // emitted at the bottom — admin can reorder later if they care.
        $bySection = [];
        foreach ($remaining as $fullKey => $value) {
            if (str_contains($fullKey, self::SECTION_KEY_SEPARATOR)) {
                [$section, $key] = explode(self::SECTION_KEY_SEPARATOR, $fullKey, 2);
            } else {
                $section = '';
                $key = $fullKey;
            }
            $bySection[$section][] = "{$key}=" . $this->scalarToString($value);
        }
        foreach ($bySection as $section => $kvs) {
            if ($section !== '') {
                $lines[] = '';
                $lines[] = "[{$section}]";
            }
            foreach ($kvs as $kv) {
                $lines[] = $kv;
            }
        }

        return implode("\n", $lines);
    }

    // -- json ------------------------------------------------------------

    /**
     * @return array<string, scalar|null>
     */
    private function parseJson(string $content): array
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid JSON content');
        }

        return $this->flattenForJson($decoded);
    }

    /**
     * @param  array<string, scalar|null>  $values
     */
    private function serializeJson(array $values): string
    {
        $nested = $this->unflattenForJson($values);
        $encoded = json_encode($nested, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Failed to encode JSON');
        }
        return $encoded;
    }

    /**
     * @param  array<string, mixed>  $array
     * @param  string  $prefix
     * @return array<string, scalar|null>
     */
    private function flattenForJson(array $array, string $prefix = ''): array
    {
        $out = [];
        foreach ($array as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value) && ! array_is_list($value)) {
                $out += $this->flattenForJson($value, $fullKey);
            } else {
                // Non-scalar leaf (list, object) — JSON-encode it back so
                // the round-trip is lossless. The UI shows it as raw text
                // and the admin can decide to expose it or hide it.
                $out[$fullKey] = is_scalar($value) || $value === null
                    ? $value
                    : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }
        return $out;
    }

    /**
     * @param  array<string, scalar|null>  $flat
     * @return array<string, mixed>
     */
    private function unflattenForJson(array $flat): array
    {
        $out = [];
        foreach ($flat as $fullKey => $value) {
            $segments = explode('.', $fullKey);
            $cursor = &$out;
            $last = array_pop($segments);
            foreach ($segments as $segment) {
                if (! isset($cursor[$segment]) || ! is_array($cursor[$segment])) {
                    $cursor[$segment] = [];
                }
                $cursor = &$cursor[$segment];
            }
            $cursor[$last] = $value;
            unset($cursor);
        }
        return $out;
    }

    // -- helpers ---------------------------------------------------------

    private function scalarToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        return (string) $value;
    }
}
