<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Parsing;

use Plugins\EasyConfiguration\Support\ConfigParameter;
use Plugins\EasyConfiguration\Support\ParsedConfig;

/**
 * Standard JSON config files. Parsed into dotted-path leaves (section = null),
 * e.g. `{"server":{"port":25565}}` -> key `server.port`.
 *
 * JSON has no comments, so "lossless" here means: key ORDER is preserved (PHP
 * associative arrays keep insertion order) and value TYPES are preserved on
 * write (a number stays a number, a bool stays a bool). Whitespace is
 * normalised to 4-space pretty print. JSONC/JSON5 are out of scope.
 */
final class JsonFormat implements ConfigFormat
{
    public function format(): string
    {
        return 'json';
    }

    public function parse(string $raw): ParsedConfig
    {
        $data = $this->decode($raw);
        $parameters = [];
        $this->flatten($data, '', $parameters);

        return new ParsedConfig($parameters);
    }

    public function apply(string $raw, array $changes): string
    {
        if ($changes === []) {
            return $raw;
        }

        $data = $this->decode($raw);

        foreach ($changes as $change) {
            $path = explode('.', $change->key);
            $original = $this->dataGet($data, $path);
            $this->dataSet($data, $path, $this->coerce($change->value, $original));
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return ($json === false ? $raw : $json)."\n";
    }

    /** @return array<string, mixed> */
    private function decode(string $raw): array
    {
        $trimmed = ltrim($raw, "\xEF\xBB\xBF \t\r\n");
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<ConfigParameter>  $out
     */
    private function flatten(array $data, string $prefix, array &$out): void
    {
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value) && $this->isAssoc($value)) {
                $this->flatten($value, $path, $out);

                continue;
            }

            $out[] = new ConfigParameter($path, $this->stringify($value));
        }
    }

    /** @param array<int|string, mixed> $value */
    private function isAssoc(array $value): bool
    {
        return $value !== [] && ! array_is_list($value);
    }

    private function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return '';
        }
        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    private function coerce(string $value, mixed $original): mixed
    {
        if (is_bool($original)) {
            return in_array(strtolower(trim($value)), ['true', '1', 'yes', 'on'], true);
        }
        if (is_int($original)) {
            return is_numeric($value) ? (int) $value : $value;
        }
        if (is_float($original)) {
            return is_numeric($value) ? (float) $value : $value;
        }
        if (is_array($original)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : $value;
        }
        if (is_string($original)) {
            return $value;
        }

        // New path (no original): infer a sensible JSON type.
        $lower = strtolower(trim($value));
        if ($lower === 'true' || $lower === 'false') {
            return $lower === 'true';
        }
        if ($value !== '' && is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $path
     */
    private function dataGet(array $data, array $path): mixed
    {
        $cursor = $data;
        foreach ($path as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $path
     */
    private function dataSet(array &$data, array $path, mixed $value): void
    {
        $cursor = &$data;
        foreach ($path as $i => $segment) {
            if ($i === count($path) - 1) {
                $cursor[$segment] = $value;

                return;
            }
            if (! isset($cursor[$segment]) || ! is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor = &$cursor[$segment];
        }
    }
}
