<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Parsing\Concerns;

use Plugins\EasyConfiguration\Support\ConfigChange;

/**
 * Insertion half of the `xml-property` writer: turns changes whose key is not
 * in the file yet into zero-length edits that append a fresh
 * `<property name="…" value="…"/>` row to the change's section — after the
 * section's last existing property when it has any, else right before the
 * section's closing tag. Games add settings over time (e.g. 7DTD's
 * SandboxCode) and files generated before them must still accept the value.
 *
 * Expects the using class to provide `escapeAttr()` (from ScansXml).
 */
trait AppendsXmlProperties
{
    /**
     * Multiple missing keys of the same section land at the same point, in
     * submitted order.
     *
     * @param  list<array{section: ?string, key: string, value: string, start: int, len: int, quote: string}>  $slots
     * @param  list<ConfigChange>  $missing
     * @return list<array{start: int, len: int, replacement: string}>
     */
    private function insertionEdits(string $raw, array $slots, array $missing): array
    {
        $inserts = [];
        foreach ($missing as $change) {
            $section = $change->section;
            // Only first occurrences of a sectioned key can be created.
            if ($section === null || $section === '' || $change->occurrence > 0) {
                continue;
            }
            $anchor = $this->insertionPoint($raw, $slots, $section);
            if ($anchor === null) {
                continue;
            }
            [$pos, $prefix, $suffix] = $anchor;
            $row = '<property name="'.$this->escapeAttr($change->key, '"').'" value="'.$this->escapeAttr($change->value, '"').'"/>';
            $inserts[$pos][] = $prefix.$row.$suffix;
        }

        $edits = [];
        foreach ($inserts as $pos => $rows) {
            $edits[] = ['start' => $pos, 'len' => 0, 'replacement' => implode('', $rows)];
        }

        return $edits;
    }

    /**
     * Locate where a new property row of `$section` goes, as
     * [byte offset, text before the row, text after the row].
     *
     * @param  list<array{section: ?string, key: string, value: string, start: int, len: int, quote: string}>  $slots
     * @return array{0: int, 1: string, 2: string}|null
     */
    private function insertionPoint(string $raw, array $slots, string $section): ?array
    {
        $last = null;
        foreach ($slots as $slot) {
            if ($slot['section'] === $section) {
                $last = $slot;
            }
        }

        if ($last !== null) {
            $tagEnd = strpos($raw, '>', $last['start'] + $last['len']);
            if ($tagEnd === false) {
                return null;
            }
            $tagEnd++;
            $close = $this->sectionClose($raw, $section, $tagEnd);
            $lineEnd = strpos($raw, "\n", $tagEnd);
            if ($lineEnd !== false && ($close === null || $lineEnd < $close)) {
                // New row on its own line, mirroring the last row's indentation
                // (past any trailing comment sharing that line).
                return [$lineEnd + 1, $this->lineIndent($raw, $last['start']), "\n"];
            }

            // Single-line section: squeeze the row right after the last one.
            return [$tagEnd, ' ', ''];
        }

        $close = $this->sectionClose($raw, $section, 0);
        if ($close === null) {
            return null;
        }
        $lineStart = $this->lineStart($raw, $close);
        if (trim(substr($raw, $lineStart, $close - $lineStart)) === '') {
            // `</Section>` sits on its own line: insert above it, one level in.
            return [$lineStart, $this->lineIndent($raw, $close)."\t", "\n"];
        }

        return [$close, '', ''];
    }

    /** Byte offset of the first `</section>` at/after `$from`, or null. */
    private function sectionClose(string $raw, string $section, int $from): ?int
    {
        $pattern = '/<\/\s*'.preg_quote($section, '/').'\s*>/i';
        if (preg_match($pattern, $raw, $match, PREG_OFFSET_CAPTURE, $from) !== 1) {
            return null;
        }

        return (int) $match[0][1];
    }

    private function lineStart(string $raw, int $pos): int
    {
        $nl = strrpos(substr($raw, 0, $pos), "\n");

        return $nl === false ? 0 : $nl + 1;
    }

    private function lineIndent(string $raw, int $pos): string
    {
        $start = $this->lineStart($raw, $pos);
        $end = $start;
        $len = strlen($raw);
        while ($end < $len && ($raw[$end] === ' ' || $raw[$end] === "\t")) {
            $end++;
        }

        return substr($raw, $start, $end - $start);
    }
}
