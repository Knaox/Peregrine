<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Parsing\Concerns;

/**
 * Helpers for line-oriented, lossless rewriting. Lines are kept WITH their
 * trailing EOL so that re-joining is byte-identical to the source (mixed or
 * `\r\n` endings survive untouched). The body/EOL split lets a format edit
 * only the value part of a single line.
 */
trait EditsRawLines
{
    /**
     * Split into chunks, each ending with its own EOL (the final chunk may
     * have none). The empty trailing element after a final newline is dropped,
     * so `implode('', split($raw)) === $raw` always holds.
     *
     * @return list<string>
     */
    protected function splitLines(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/(?<=\n)/', $raw);
        if ($parts === false) {
            return [$raw];
        }

        return array_values(array_filter($parts, static fn (string $p): bool => $p !== ''));
    }

    protected function eolOf(string $chunk): string
    {
        if (str_ends_with($chunk, "\r\n")) {
            return "\r\n";
        }

        return str_ends_with($chunk, "\n") ? "\n" : '';
    }

    /** The line content without its trailing EOL. */
    protected function bodyOf(string $chunk): string
    {
        $eol = $this->eolOf($chunk);

        return $eol === '' ? $chunk : substr($chunk, 0, -strlen($eol));
    }

    /** Dominant EOL of the document, for appended lines. Defaults to "\n". */
    protected function dominantEol(string $raw): string
    {
        return substr_count($raw, "\r\n") > 0 ? "\r\n" : "\n";
    }

    /**
     * Append a fully-formed line to the document, guaranteeing the previous
     * content ends with a newline first so we never glue onto a no-EOL last
     * line. Returns the new raw string.
     */
    protected function appendLine(string $raw, string $line, string $eol): string
    {
        if ($raw !== '' && ! str_ends_with($raw, "\n")) {
            $raw .= $eol;
        }

        return $raw.$line.$eol;
    }
}
