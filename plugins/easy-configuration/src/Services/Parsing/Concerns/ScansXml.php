<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Parsing\Concerns;

/**
 * A tiny, dependency-free XML tokeniser geared for lossless value substitution.
 * It walks the raw document once and yields an ordered list of editable "value
 * slots": either an element's text content (key = dotted element path, e.g.
 * `server.port`) or an attribute (key = `path@attr`). Each slot carries the byte
 * offset + length of its RAW value substring so a writer can replace just that
 * span, leaving every comment, attribute order, indentation and unrelated byte
 * untouched. DOMDocument is deliberately avoided: it reformats on serialise and
 * cannot guarantee a byte-identical round-trip.
 *
 * Only text-only (leaf) elements and attributes are surfaced. Containers, mixed
 * content (text + child elements), comments, CDATA, processing instructions and
 * the prolog are scanned over but never offered as editable values.
 */
trait ScansXml
{
    /**
     * @return list<array{key: string, value: string, start: int, len: int, attr: bool, quote: string}>
     */
    protected function scanSlots(string $raw): array
    {
        $slots = [];
        $len = strlen($raw);
        $i = 0;
        /** @var list<array{path: string, hasChild: bool, hasText: bool, textStart: int, textEnd: int}> $stack */
        $stack = [];

        while ($i < $len) {
            if ($raw[$i] !== '<') {
                $j = strpos($raw, '<', $i);
                $j = $j === false ? $len : $j;
                if ($stack !== []) {
                    $top = count($stack) - 1;
                    if (! $stack[$top]['hasText']) {
                        [$ts, $te] = $this->trimSpan($raw, $i, $j);
                        $stack[$top]['textStart'] = $ts;
                        $stack[$top]['textEnd'] = $te;
                        $stack[$top]['hasText'] = true;
                    }
                }
                $i = $j;

                continue;
            }

            $skip = $this->skipNonElement($raw, $i, $len);
            if ($skip !== null) {
                // CDATA is real content: the enclosing element is not a simple
                // leaf, so we must never clobber it with a text substitution.
                if ($skip['cdata'] && $stack !== []) {
                    $stack[count($stack) - 1]['hasChild'] = true;
                }
                $i = $skip['next'];

                continue;
            }

            if ($raw[$i + 1] === '/') {
                $end = strpos($raw, '>', $i);
                $end = $end === false ? $len : $end;
                if ($stack !== []) {
                    $frame = array_pop($stack);
                    if (! $frame['hasChild']) {
                        $start = $frame['textStart'];
                        $stop = $frame['textEnd'];
                        $slots[] = [
                            'key' => $frame['path'],
                            'value' => $this->decode(substr($raw, $start, $stop - $start)),
                            'start' => $start,
                            'len' => $stop - $start,
                            'attr' => false,
                            'quote' => '',
                        ];
                    }
                }
                $i = $end + 1;

                continue;
            }

            $tag = $this->parseStartTag($raw, $i, $len);
            if ($tag === null) {
                $i++;

                continue;
            }

            $parent = $stack === [] ? '' : $stack[count($stack) - 1]['path'];
            $path = $parent === '' ? $tag['name'] : $parent.'.'.$tag['name'];

            if ($stack !== []) {
                $stack[count($stack) - 1]['hasChild'] = true;
            }

            foreach ($tag['attrs'] as $attr) {
                $slots[] = [
                    'key' => $path.'@'.$attr['name'],
                    'value' => $this->decode(substr($raw, $attr['start'], $attr['len'])),
                    'start' => $attr['start'],
                    'len' => $attr['len'],
                    'attr' => true,
                    'quote' => $attr['quote'],
                ];
            }

            if (! $tag['selfClosing']) {
                $stack[] = [
                    'path' => $path,
                    'hasChild' => false,
                    'hasText' => false,
                    'textStart' => $tag['end'],
                    'textEnd' => $tag['end'],
                ];
            }
            $i = $tag['end'];
        }

        return $slots;
    }

    /**
     * Skip a non-element construct (comment, CDATA, PI, DOCTYPE) starting at $i.
     * Returns the index just past it (+ whether it was CDATA) or null when $i is
     * a real element tag.
     *
     * @return array{next: int, cdata: bool}|null
     */
    private function skipNonElement(string $raw, int $i, int $len): ?array
    {
        $pairs = [
            ['<!--', '-->', false],
            ['<![CDATA[', ']]>', true],
            ['<?', '?>', false],
            ['<!', '>', false],
        ];
        foreach ($pairs as [$open, $close, $cdata]) {
            if (substr($raw, $i, strlen($open)) === $open) {
                $end = strpos($raw, $close, $i);

                return ['next' => $end === false ? $len : $end + strlen($close), 'cdata' => $cdata];
            }
        }

        return null;
    }

    /**
     * @return array{name: string, attrs: list<array{name: string, start: int, len: int, quote: string}>, end: int, selfClosing: bool}|null
     */
    private function parseStartTag(string $raw, int $i, int $len): ?array
    {
        $p = $i + 1;
        $start = $p;
        while ($p < $len && ! ctype_space($raw[$p]) && $raw[$p] !== '>' && $raw[$p] !== '/') {
            $p++;
        }
        $name = substr($raw, $start, $p - $start);
        if ($name === '') {
            return null;
        }

        $attrs = [];
        while ($p < $len) {
            while ($p < $len && ctype_space($raw[$p])) {
                $p++;
            }
            if ($p >= $len) {
                return null;
            }
            if ($raw[$p] === '>') {
                return ['name' => $name, 'attrs' => $attrs, 'end' => $p + 1, 'selfClosing' => false];
            }
            if ($raw[$p] === '/') {
                if ($p + 1 < $len && $raw[$p + 1] === '>') {
                    return ['name' => $name, 'attrs' => $attrs, 'end' => $p + 2, 'selfClosing' => true];
                }
                $p++;

                continue;
            }

            $as = $p;
            while ($p < $len && $raw[$p] !== '=' && ! ctype_space($raw[$p]) && $raw[$p] !== '>' && $raw[$p] !== '/') {
                $p++;
            }
            $attrName = substr($raw, $as, $p - $as);
            while ($p < $len && ctype_space($raw[$p])) {
                $p++;
            }
            if ($p < $len && $raw[$p] === '=') {
                $p++;
                while ($p < $len && ctype_space($raw[$p])) {
                    $p++;
                }
                if ($p < $len && ($raw[$p] === '"' || $raw[$p] === "'")) {
                    $quote = $raw[$p];
                    $vs = ++$p;
                    while ($p < $len && $raw[$p] !== $quote) {
                        $p++;
                    }
                    $attrs[] = ['name' => $attrName, 'start' => $vs, 'len' => $p - $vs, 'quote' => $quote];
                    $p++;
                }
            }
        }

        return null;
    }

    /** @return array{0: int, 1: int} trimmed [start, end) within [$from, $to). */
    private function trimSpan(string $raw, int $from, int $to): array
    {
        $s = $from;
        while ($s < $to && ctype_space($raw[$s])) {
            $s++;
        }
        $e = $to;
        while ($e > $s && ctype_space($raw[$e - 1])) {
            $e--;
        }

        return [$s, $e];
    }

    private function decode(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    protected function escapeText(string $value): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }

    protected function escapeAttr(string $value, string $quote): string
    {
        $value = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);

        return $quote === "'"
            ? str_replace("'", '&apos;', $value)
            : str_replace('"', '&quot;', $value);
    }
}
