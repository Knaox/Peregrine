<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Parsing;

use Plugins\EasyConfiguration\Support\ConfigChange;
use Plugins\EasyConfiguration\Support\ConfigParameter;
use Plugins\EasyConfiguration\Support\ParsedConfig;

// OptionSettings lives under a section, so it's resolved by key across all
// parameters rather than via ParsedConfig::get() (which defaults section=null).

/**
 * Palworld `PalWorldSettings.ini`. A normal INI whose single meaningful line is
 *
 *   [/Script/Pal.PalGameWorldSettings]
 *   OptionSettings=(Difficulty=None,ServerName="My, server",DayTimeSpeedRate=1.0,...)
 *
 * Each inner `Key=Value` pair is exposed as an individually editable parameter
 * (its section is the OptionSettings section) and recomposed back into the one
 * line on write. Top-level commas split pairs; commas inside double quotes are
 * kept. Each value's original quoting is preserved on write, and values are
 * shown unquoted for editing. The outer INI file is handled by IniFormat.
 */
final class PalworldFormat implements ConfigFormat
{
    private const KEY = 'OptionSettings';

    private readonly IniFormat $ini;

    public function __construct()
    {
        $this->ini = new IniFormat;
    }

    public function format(): string
    {
        return 'palworld';
    }

    public function parse(string $raw): ParsedConfig
    {
        $parameters = [];
        foreach ($this->ini->parse($raw)->parameters as $param) {
            if ($param->key !== self::KEY) {
                $parameters[] = $param;

                continue;
            }
            foreach ($this->splitPairs($this->innerOf($param->value)) as [$key, $value]) {
                $parameters[] = new ConfigParameter($key, $this->unquote($value), $param->section);
            }
        }

        return new ParsedConfig($parameters);
    }

    public function apply(string $raw, array $changes): string
    {
        $current = $this->container($this->ini->parse($raw));
        if ($current === null) {
            return $this->ini->apply($raw, $changes); // no OptionSettings line — pass through
        }
        $section = $current->section;

        $inner = [];
        $passthrough = [];
        foreach ($changes as $change) {
            if ($change->section === $section) {
                $inner[$change->key] = $change->value;
            } else {
                $passthrough[] = $change;
            }
        }

        if ($inner !== []) {
            $rebuilt = $this->rebuild($this->splitPairs($this->innerOf($current->value)), $inner);
            $passthrough[] = new ConfigChange(self::KEY, $rebuilt, $section);
        }

        return $this->ini->apply($raw, $passthrough);
    }

    /** The OptionSettings parameter, found by key across any section. */
    private function container(ParsedConfig $parsed): ?ConfigParameter
    {
        foreach ($parsed->parameters as $param) {
            if ($param->key === self::KEY) {
                return $param;
            }
        }

        return null;
    }

    /** Content between the outermost parentheses, or '' when absent. */
    private function innerOf(string $value): string
    {
        $start = strpos($value, '(');
        $end = strrpos($value, ')');
        if ($start === false || $end === false || $end <= $start) {
            return '';
        }

        return substr($value, $start + 1, $end - $start - 1);
    }

    /**
     * Ordered [key, rawValue] pairs from `K=V,K="a,b",...` (top-level commas only).
     *
     * @return list<array{0: string, 1: string}>
     */
    private function splitPairs(string $inner): array
    {
        $pairs = [];
        foreach ($this->splitTop($inner) as $segment) {
            $eq = strpos($segment, '=');
            if ($eq === false) {
                continue;
            }
            $pairs[] = [trim(substr($segment, 0, $eq)), trim(substr($segment, $eq + 1))];
        }

        return $pairs;
    }

    /** @return list<string> */
    private function splitTop(string $inner): array
    {
        $segments = [];
        $buffer = '';
        $inQuotes = false;
        $length = strlen($inner);
        for ($i = 0; $i < $length; $i++) {
            $char = $inner[$i];
            if ($char === '"') {
                $inQuotes = ! $inQuotes;
                $buffer .= $char;
            } elseif ($char === ',' && ! $inQuotes) {
                $segments[] = $buffer;
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }
        if (trim($buffer) !== '') {
            $segments[] = $buffer;
        }

        return $segments;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $pairs
     * @param  array<string, string>  $changes  key => new (unquoted) value
     */
    private function rebuild(array $pairs, array $changes): string
    {
        $seen = [];
        $out = [];
        foreach ($pairs as [$key, $rawValue]) {
            $seen[$key] = true;
            $out[] = $key.'='.(array_key_exists($key, $changes) ? $this->requote($rawValue, $changes[$key]) : $rawValue);
        }
        foreach ($changes as $key => $value) {
            if (! isset($seen[$key])) {
                $out[] = $key.'='.$this->maybeQuote($value);
            }
        }

        return '('.implode(',', $out).')';
    }

    private function unquote(string $value): string
    {
        return $this->isQuoted($value) ? substr($value, 1, -1) : $value;
    }

    /** Keep the original quoting style when writing a changed value. */
    private function requote(string $originalRaw, string $newValue): string
    {
        return $this->isQuoted($originalRaw) ? '"'.$newValue.'"' : $this->maybeQuote($newValue);
    }

    /** Quote a fresh value only when leaving it bare would break the structure. */
    private function maybeQuote(string $value): string
    {
        if ($this->isQuoted($value)) {
            return $value;
        }

        return preg_match('/[,()"\s]/', $value) === 1 ? '"'.$value.'"' : $value;
    }

    private function isQuoted(string $value): bool
    {
        return strlen($value) >= 2 && str_starts_with($value, '"') && str_ends_with($value, '"');
    }
}
