<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Parsing;

use Plugins\EasyConfiguration\Services\Parsing\Concerns\EditsRawLines;
use Plugins\EasyConfiguration\Support\ConfigParameter;
use Plugins\EasyConfiguration\Support\ParsedConfig;

/**
 * Java `.properties` (Minecraft server.properties, …): flat `key=value` lines,
 * comments start with `#` or `!`. Separator is the first `=` or `:`.
 * Flat format -> every parameter has a null section.
 */
final class PropertiesFormat implements ConfigFormat
{
    use EditsRawLines;

    public function format(): string
    {
        return 'properties';
    }

    public function parse(string $raw): ParsedConfig
    {
        $parameters = [];
        $counts = []; // key => occurrences seen so far

        foreach ($this->splitLines($this->stripBom($raw)) as $chunk) {
            $body = $this->bodyOf($chunk);
            $sep = $this->separatorIndex($body);
            if ($sep === null) {
                continue;
            }

            $key = trim(substr($body, 0, $sep));
            $occurrence = $counts[$key] ?? 0;
            $counts[$key] = $occurrence + 1;
            $parameters[] = new ConfigParameter($key, trim(substr($body, $sep + 1)), null, $occurrence);
        }

        return new ParsedConfig($parameters);
    }

    public function apply(string $raw, array $changes): string
    {
        if ($changes === []) {
            return $raw;
        }

        /** @var array<string, array<int, string>> $pending key => [occurrence => value] */
        $pending = [];
        foreach ($changes as $change) {
            $pending[$change->key][$change->occurrence] = $change->value;
        }

        $chunks = $this->splitLines($raw);
        $seen = []; // key => occurrences walked so far
        foreach ($chunks as $i => $chunk) {
            $eol = $this->eolOf($chunk);
            $body = $this->bodyOf($chunk);
            $sep = $this->separatorIndex($body);
            if ($sep === null) {
                continue;
            }

            $key = trim(substr($body, 0, $sep));
            $occurrence = $seen[$key] ?? 0;
            $seen[$key] = $occurrence + 1;
            if (! isset($pending[$key][$occurrence])) {
                continue;
            }

            // Keep everything up to & including the separator and the value's
            // leading whitespace; replace only the value token.
            $head = substr($body, 0, $sep + 1);
            $rest = substr($body, $sep + 1);
            $lead = substr($rest, 0, strlen($rest) - strlen(ltrim($rest)));
            $chunks[$i] = $head.$lead.$pending[$key][$occurrence].$eol;
            unset($pending[$key][$occurrence]);
            if ($pending[$key] === []) {
                unset($pending[$key]);
            }
        }

        $result = implode('', $chunks);

        $appendEol = $this->dominantEol($raw);
        foreach ($pending as $key => $occValues) {
            foreach ($occValues as $value) {
                $result = $this->appendLine($result, $key.'='.$value, $appendEol);
            }
        }

        return $result;
    }

    /** First `=` or `:` on a non-comment line; null for blanks/comments. */
    private function separatorIndex(string $body): ?int
    {
        $trimmedStart = ltrim($body);
        if ($trimmedStart === '' || $trimmedStart[0] === '#' || $trimmedStart[0] === '!') {
            return null;
        }

        $eq = strpos($body, '=');
        $colon = strpos($body, ':');

        if ($eq === false) {
            return $colon === false ? null : $colon;
        }
        if ($colon === false) {
            return $eq;
        }

        return min($eq, $colon);
    }

    private function stripBom(string $raw): string
    {
        return str_starts_with($raw, "\xEF\xBB\xBF") ? substr($raw, 3) : $raw;
    }
}
