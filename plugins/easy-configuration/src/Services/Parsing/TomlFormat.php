<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Parsing;

use Plugins\EasyConfiguration\Services\Parsing\Concerns\EditsRawLines;
use Plugins\EasyConfiguration\Services\Parsing\Concerns\HandlesInlineValues;
use Plugins\EasyConfiguration\Support\ConfigParameter;
use Plugins\EasyConfiguration\Support\ParsedConfig;

/**
 * TOML config files (Forge mod configs, …). Supports `[table]` / `[a.b]` tables
 * and scalar `key = value` lines with `#` comments (including inline comments).
 *
 * The writer replaces only the value token, preserving the key, spacing, the
 * original quoting style of strings, and any trailing inline comment. Arrays
 * and inline tables are read as raw text and written back verbatim unless the
 * new value is itself a valid fragment. Triple-quoted strings are out of scope.
 */
final class TomlFormat implements ConfigFormat
{
    use EditsRawLines;
    use HandlesInlineValues;

    public function format(): string
    {
        return 'toml';
    }

    public function parse(string $raw): ParsedConfig
    {
        $parameters = [];
        $table = null;

        foreach ($this->splitLines($this->stripBom($raw)) as $chunk) {
            $body = $this->bodyOf($chunk);
            $header = $this->tableHeader($body);
            if ($header !== null) {
                $table = $header;

                continue;
            }

            $eq = $this->keyEquals($body);
            if ($eq === null) {
                continue;
            }

            $key = trim(substr($body, 0, $eq));
            [, $token] = $this->splitValue(substr($body, $eq + 1));
            $parameters[] = new ConfigParameter($key, $this->displayValue($token), $table);
        }

        return new ParsedConfig($parameters);
    }

    public function apply(string $raw, array $changes): string
    {
        if ($changes === []) {
            return $raw;
        }

        /** @var array<string, array<string, string>> $pending tableKey => [key => value] */
        $pending = [];
        foreach ($changes as $change) {
            $pending[$change->section ?? ''][$change->key] = $change->value;
        }

        $eol = $this->dominantEol($raw);
        $table = null;
        $out = [];

        foreach ($this->splitLines($raw) as $chunk) {
            $body = $this->bodyOf($chunk);
            $lineEol = $this->eolOf($chunk);

            $header = $this->tableHeader($body);
            if ($header !== null) {
                $this->flushTable($out, $pending, $table, $eol);
                $table = $header;
                $out[] = $chunk;

                continue;
            }

            $eq = $this->keyEquals($body);
            $tk = $table ?? '';
            if ($eq !== null && isset($pending[$tk][trim(substr($body, 0, $eq))])) {
                $key = trim(substr($body, 0, $eq));
                $head = substr($body, 0, $eq + 1);
                [$lead, $token, $trailing] = $this->splitValue(substr($body, $eq + 1));
                $newToken = $this->rewriteToken($token, $pending[$tk][$key]);
                $out[] = $head.$lead.$newToken.$trailing.$lineEol;
                unset($pending[$tk][$key]);

                continue;
            }

            $out[] = $chunk;
        }

        $this->flushTable($out, $pending, $table, $eol);
        $result = implode('', $out);

        foreach ($pending as $tk => $entries) {
            if ($entries === []) {
                continue;
            }
            if ($tk !== '') {
                $result = $this->appendLine($result, '['.$tk.']', $eol);
            }
            foreach ($entries as $key => $value) {
                $result = $this->appendLine($result, $key.' = '.$this->rewriteToken('', $value), $eol);
            }
        }

        return $result;
    }

    /**
     * @param  list<string>  $out
     * @param  array<string, array<string, string>>  $pending
     */
    private function flushTable(array &$out, array &$pending, ?string $table, string $eol): void
    {
        $tk = $table ?? '';
        if (empty($pending[$tk])) {
            return;
        }

        $this->ensureTrailingEol($out, $eol);
        foreach ($pending[$tk] as $key => $value) {
            $out[] = $key.' = '.$this->rewriteToken('', $value).$eol;
        }
        $pending[$tk] = [];
    }

    private function tableHeader(string $body): ?string
    {
        if (preg_match('/^\s*\[\[(.+?)\]\]\s*$/', $body, $m) === 1) {
            return $m[1];
        }
        if (preg_match('/^\s*\[(.+?)\]\s*$/', $body, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function keyEquals(string $body): ?int
    {
        $start = ltrim($body);
        if ($start === '' || $start[0] === '#' || $start[0] === '[') {
            return null;
        }

        $eq = strpos($body, '=');

        return $eq === false ? null : $eq;
    }

    private function stripBom(string $raw): string
    {
        return str_starts_with($raw, "\xEF\xBB\xBF") ? substr($raw, 3) : $raw;
    }
}
