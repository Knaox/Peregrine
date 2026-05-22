<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Parsing;

use Plugins\EasyConfiguration\Services\Parsing\Concerns\EditsRawLines;
use Plugins\EasyConfiguration\Services\Parsing\Concerns\HandlesInlineValues;
use Plugins\EasyConfiguration\Support\ConfigParameter;
use Plugins\EasyConfiguration\Support\ParsedConfig;

/**
 * YAML config files (Paper/Spigot/Bukkit config.yml, …). Nested mappings are
 * flattened to dotted-path leaves (section = null), e.g. `world.spawn-radius`.
 *
 * No PHP library round-trips YAML comments, so this is a hand-rolled,
 * indentation-aware scanner: it reads scalar leaves for the UI and rewrites a
 * value in place, preserving indentation, key, quoting and inline comments.
 * Supported subset (covers real game configs): nested scalar mappings. List
 * items (`- x`) and null/empty parents are not surfaced as editable leaves.
 * Appending only creates missing TOP-LEVEL keys; deep keys are left untouched.
 */
final class YamlFormat implements ConfigFormat
{
    use EditsRawLines;
    use HandlesInlineValues;

    public function format(): string
    {
        return 'yaml';
    }

    public function parse(string $raw): ParsedConfig
    {
        $chunks = $this->splitLines($this->stripBom($raw));
        $parameters = [];

        foreach ($this->scan($chunks) as $leaf) {
            $body = $this->bodyOf($chunks[$leaf['index']]);
            [, $token] = $this->splitValue(substr($body, $leaf['sep'] + 1));
            $parameters[] = new ConfigParameter($leaf['path'], $this->displayValue($token));
        }

        return new ParsedConfig($parameters);
    }

    public function apply(string $raw, array $changes): string
    {
        if ($changes === []) {
            return $raw;
        }

        $pending = [];
        foreach ($changes as $change) {
            $pending[$change->key] = $change->value;
        }

        $chunks = $this->splitLines($raw);
        foreach ($this->scan($chunks) as $leaf) {
            if (! array_key_exists($leaf['path'], $pending)) {
                continue;
            }

            $chunk = $chunks[$leaf['index']];
            $eol = $this->eolOf($chunk);
            $body = $this->bodyOf($chunk);
            $head = substr($body, 0, $leaf['sep'] + 1);
            [$lead, $token, $trailing] = $this->splitValue(substr($body, $leaf['sep'] + 1));
            $newToken = $this->rewriteToken($token, $pending[$leaf['path']]);
            $chunks[$leaf['index']] = $head.$lead.$newToken.$trailing.$eol;
            unset($pending[$leaf['path']]);
        }

        $result = implode('', $chunks);

        $eol = $this->dominantEol($raw);
        foreach ($pending as $key => $value) {
            if (str_contains($key, '.')) {
                continue; // deep keys aren't created (best-effort, documented)
            }
            $result = $this->appendLine($result, $key.': '.$this->rewriteToken('', $value), $eol);
        }

        return $result;
    }

    /**
     * Walk lines maintaining an indentation stack of parent keys; emit one
     * entry per scalar leaf: its chunk index, dotted path, and the index of the
     * mapping `:` separator within the line body.
     *
     * @param  list<string>  $chunks
     * @return list<array{index: int, path: string, sep: int}>
     */
    private function scan(array $chunks): array
    {
        /** @var list<array{indent: int, key: string}> $stack */
        $stack = [];
        $leaves = [];

        foreach ($chunks as $i => $chunk) {
            $body = $this->bodyOf($chunk);
            $trimmed = ltrim($body, ' ');
            if ($trimmed === '' || $trimmed[0] === '#' || str_starts_with($trimmed, '- ') || $trimmed === '-') {
                continue;
            }

            $sep = $this->separatorIndex($body);
            if ($sep === null) {
                continue;
            }

            $indent = strlen($body) - strlen($trimmed);
            while ($stack !== [] && $stack[count($stack) - 1]['indent'] >= $indent) {
                array_pop($stack);
            }

            $key = trim(substr($body, 0, $sep));
            $valuePart = ltrim(substr($body, $sep + 1));

            if ($valuePart === '' || $valuePart[0] === '#') {
                $stack[] = ['indent' => $indent, 'key' => $key];

                continue;
            }

            $path = $key;
            foreach (array_reverse($stack) as $parent) {
                $path = $parent['key'].'.'.$path;
            }
            $leaves[] = ['index' => $i, 'path' => $path, 'sep' => $sep];
        }

        return $leaves;
    }

    /** First mapping `:` (followed by space/EOL, outside quotes); null otherwise. */
    private function separatorIndex(string $body): ?int
    {
        $len = strlen($body);
        $quote = '';
        for ($i = 0; $i < $len; $i++) {
            $c = $body[$i];
            if ($quote !== '') {
                if ($c === $quote) {
                    $quote = '';
                }

                continue;
            }
            if ($c === '"' || $c === "'") {
                $quote = $c;

                continue;
            }
            if ($c === ':' && ($i + 1 >= $len || $body[$i + 1] === ' ' || $body[$i + 1] === "\t")) {
                return $i;
            }
        }

        return null;
    }

    private function stripBom(string $raw): string
    {
        return str_starts_with($raw, "\xEF\xBB\xBF") ? substr($raw, 3) : $raw;
    }
}
