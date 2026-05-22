<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Parsing\Concerns;

/**
 * Shared value-token handling for the quote-aware, comment-aware formats
 * (TOML, YAML). Splits the text after a `=`/`:` into [leadingWhitespace,
 * valueToken, trailing] where `trailing` keeps any spaces + inline `#` comment,
 * so a rewrite touches only the value and leaves the comment intact. The token
 * preserves the original quoting style of strings.
 */
trait HandlesInlineValues
{
    /** @return array{0: string, 1: string, 2: string} */
    protected function splitValue(string $after): array
    {
        $lead = substr($after, 0, strlen($after) - strlen(ltrim($after)));
        $rest = ltrim($after);
        if ($rest === '') {
            return [$lead, '', ''];
        }

        $first = $rest[0];
        if ($first === '"' || $first === "'") {
            $end = $this->closingQuote($rest, $first);
            $token = substr($rest, 0, $end + 1);

            return [$lead, $token, substr($rest, $end + 1)];
        }

        if ($first === '[' || $first === '{') {
            $close = $first === '[' ? ']' : '}';
            $end = $this->matchingBracket($rest, $first, $close);

            return [$lead, substr($rest, 0, $end + 1), substr($rest, $end + 1)];
        }

        $hash = $this->inlineCommentPos($rest);
        if ($hash === null) {
            $token = rtrim($rest);

            return [$lead, $token, substr($rest, strlen($token))];
        }
        $token = rtrim(substr($rest, 0, $hash));

        return [$lead, $token, substr($rest, strlen($token))];
    }

    protected function displayValue(string $token): string
    {
        if ($token === '') {
            return '';
        }
        $q = $token[0];
        if ($q === '"') {
            return stripcslashes(substr($token, 1, -1));
        }
        if ($q === "'") {
            return substr($token, 1, -1);
        }

        return $token;
    }

    protected function rewriteToken(string $original, string $newValue): string
    {
        $q = $original === '' ? '' : $original[0];

        if ($q === '"' || $q === "'") {
            if ($q === "'" && ! str_contains($newValue, "'")) {
                return "'".$newValue."'";
            }

            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $newValue).'"';
        }

        return $newValue;
    }

    private function closingQuote(string $rest, string $quote): int
    {
        $len = strlen($rest);
        for ($i = 1; $i < $len; $i++) {
            if ($rest[$i] === '\\' && $quote === '"') {
                $i++;

                continue;
            }
            if ($rest[$i] === $quote) {
                return $i;
            }
        }

        return $len - 1;
    }

    private function matchingBracket(string $rest, string $open, string $close): int
    {
        $depth = 0;
        $len = strlen($rest);
        for ($i = 0; $i < $len; $i++) {
            if ($rest[$i] === $open) {
                $depth++;
            } elseif ($rest[$i] === $close) {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return $len - 1;
    }

    private function inlineCommentPos(string $rest): ?int
    {
        $len = strlen($rest);
        for ($i = 0; $i < $len; $i++) {
            if ($rest[$i] === '#' && ($i === 0 || $rest[$i - 1] === ' ' || $rest[$i - 1] === "\t")) {
                return $i;
            }
        }

        return null;
    }
}
