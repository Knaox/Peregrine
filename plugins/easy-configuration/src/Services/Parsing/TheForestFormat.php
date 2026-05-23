<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Parsing;

use Plugins\EasyConfiguration\Services\Parsing\Concerns\EditsRawLines;
use Plugins\EasyConfiguration\Support\ConfigParameter;
use Plugins\EasyConfiguration\Support\ParsedConfig;

/**
 * The Forest `Server.cfg`: line-oriented `key value` pairs separated by the
 * first whitespace, string values usually wrapped in double quotes, `//` line
 * comments. Flat (no sections). Values are shown unquoted for editing; the
 * original quoting is preserved on write, a missing key is appended at EOF, and
 * untouched lines (including comments) survive byte-for-byte.
 */
final class TheForestFormat implements ConfigFormat
{
    use EditsRawLines;

    public function format(): string
    {
        return 'theforest';
    }

    public function parse(string $raw): ParsedConfig
    {
        $parameters = [];
        $counts = []; // key => occurrences seen so far
        foreach ($this->splitLines($raw) as $chunk) {
            $body = $this->bodyOf($chunk);
            if ($this->isSkippable($body)) {
                continue;
            }
            [$key, $value] = $this->splitKeyValue($body);
            if ($key !== '') {
                $occurrence = $counts[$key] ?? 0;
                $counts[$key] = $occurrence + 1;
                $parameters[] = new ConfigParameter($key, $this->unquote($value), null, $occurrence);
            }
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

        $eol = $this->dominantEol($raw);
        $seen = []; // key => occurrences walked so far
        $out = [];
        foreach ($this->splitLines($raw) as $chunk) {
            $body = $this->bodyOf($chunk);
            if ($this->isSkippable($body)) {
                $out[] = $chunk;

                continue;
            }
            [$key, $oldValue] = $this->splitKeyValue($body);
            if ($key !== '') {
                $occurrence = $seen[$key] ?? 0;
                $seen[$key] = $occurrence + 1;
                if (isset($pending[$key][$occurrence])) {
                    $out[] = $key.' '.$this->requote($oldValue, $pending[$key][$occurrence]).$this->eolOf($chunk);
                    unset($pending[$key][$occurrence]);
                    if ($pending[$key] === []) {
                        unset($pending[$key]);
                    }

                    continue;
                }
            }
            $out[] = $chunk;
        }

        $result = implode('', $out);
        foreach ($pending as $key => $occValues) {
            foreach ($occValues as $value) {
                $result = $this->appendLine($result, $key.' '.$this->maybeQuote($value), $eol);
            }
        }

        return $result;
    }

    private function isSkippable(string $body): bool
    {
        $trimmed = ltrim($body);

        return $trimmed === '' || str_starts_with($trimmed, '//');
    }

    /** @return array{0: string, 1: string} */
    private function splitKeyValue(string $body): array
    {
        if (preg_match('/^\s*(\S+)\s+(.*)$/', $body, $m) === 1) {
            return [$m[1], rtrim($m[2])];
        }

        return ['', ''];
    }

    private function unquote(string $value): string
    {
        return $this->isQuoted($value) ? substr($value, 1, -1) : $value;
    }

    private function requote(string $originalRaw, string $newValue): string
    {
        return $this->isQuoted($originalRaw) ? '"'.$newValue.'"' : $this->maybeQuote($newValue);
    }

    /** Quote a fresh value when it contains spaces (a multi-word string). */
    private function maybeQuote(string $value): string
    {
        if ($this->isQuoted($value)) {
            return $value;
        }

        return preg_match('/\s/', $value) === 1 ? '"'.$value.'"' : $value;
    }

    private function isQuoted(string $value): bool
    {
        return strlen($value) >= 2 && str_starts_with($value, '"') && str_ends_with($value, '"');
    }
}
