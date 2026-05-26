<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Parsing;

use Plugins\EasyConfiguration\Services\Parsing\Concerns\ScansXml;
use Plugins\EasyConfiguration\Support\ConfigParameter;
use Plugins\EasyConfiguration\Support\ParsedConfig;

/**
 * XML files built from `<property name="K" value="V"/>` rows — the convention
 * used by 7 Days to Die's `serverconfig.xml` (and similar dedicated servers):
 *
 *   <ServerSettings>
 *       <property name="ServerName"        value="My Game Host"/>
 *       <property name="ServerMaxPlayers"  value="8"/>
 *   </ServerSettings>
 *
 * Each `<property>` becomes ONE editable parameter keyed by its `name`
 * attribute, under the section of its parent element (e.g. `ServerSettings`).
 * The editable value is the `value` attribute — never the `name`. This avoids
 * the generic `xml` format's footgun of exposing both attributes of every row
 * as separate fields (which surfaces the same setting twice).
 *
 * `apply()` is lossless: it rewrites only the matched `value="…"` span, keeping
 * the row's `name`, attribute order, quote style, comments and layout intact.
 * A key absent from the file is skipped.
 */
final class XmlPropertyFormat implements ConfigFormat
{
    use ScansXml;

    private const TAG = 'property';

    public function format(): string
    {
        return 'xml-property';
    }

    public function parse(string $raw): ParsedConfig
    {
        $occurrences = [];
        $parameters = [];

        foreach ($this->scanProperties($raw) as $slot) {
            $id = ($slot['section'] ?? '')."\x1f".$slot['key'];
            $n = $occurrences[$id] ?? 0;
            $occurrences[$id] = $n + 1;
            $parameters[] = new ConfigParameter($slot['key'], $slot['value'], $slot['section'], $n);
        }

        return new ParsedConfig($parameters);
    }

    public function apply(string $raw, array $changes): string
    {
        if ($changes === []) {
            return $raw;
        }

        $slots = $this->scanProperties($raw);
        $byId = [];
        foreach ($slots as $index => $slot) {
            $byId[($slot['section'] ?? '')."\x1f".$slot['key']][] = $index;
        }

        $edits = [];
        foreach ($changes as $change) {
            $index = $byId[($change->section ?? '')."\x1f".$change->key][$change->occurrence] ?? null;
            if ($index === null) {
                continue;
            }
            $slot = $slots[$index];
            $edits[] = [
                'start' => $slot['start'],
                'len' => $slot['len'],
                'replacement' => $this->escapeAttr($change->value, $slot['quote']),
            ];
        }

        usort($edits, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);
        foreach ($edits as $edit) {
            $raw = substr($raw, 0, $edit['start']).$edit['replacement'].substr($raw, $edit['start'] + $edit['len']);
        }

        return $raw;
    }

    /**
     * Walk the document and yield one slot per `<property name=… value=…>` row:
     * the section is the parent element's name, the editable span is the `value`
     * attribute. Reuses the ScansXml tokeniser helpers.
     *
     * @return list<array{section: ?string, key: string, value: string, start: int, len: int, quote: string}>
     */
    private function scanProperties(string $raw): array
    {
        $out = [];
        $len = strlen($raw);
        $i = 0;
        /** @var list<string> $stack open element names (for the parent section) */
        $stack = [];

        while ($i < $len) {
            if ($raw[$i] !== '<') {
                $next = strpos($raw, '<', $i);
                $i = $next === false ? $len : $next;

                continue;
            }

            $skip = $this->skipNonElement($raw, $i, $len);
            if ($skip !== null) {
                $i = $skip['next'];

                continue;
            }

            if ($raw[$i + 1] === '/') {
                array_pop($stack);
                $end = strpos($raw, '>', $i);
                $i = $end === false ? $len : $end + 1;

                continue;
            }

            $tag = $this->parseStartTag($raw, $i, $len);
            if ($tag === null) {
                $i++;

                continue;
            }

            if (strcasecmp($tag['name'], self::TAG) === 0) {
                $slot = $this->propertySlot($raw, $tag['attrs'], $stack === [] ? null : $stack[count($stack) - 1]);
                if ($slot !== null) {
                    $out[] = $slot;
                }
            }

            if (! $tag['selfClosing']) {
                $stack[] = $tag['name'];
            }
            $i = $tag['end'];
        }

        return $out;
    }

    /**
     * @param  list<array{name: string, start: int, len: int, quote: string}>  $attrs
     * @return array{section: ?string, key: string, value: string, start: int, len: int, quote: string}|null
     */
    private function propertySlot(string $raw, array $attrs, ?string $section): ?array
    {
        $name = null;
        $value = null;
        foreach ($attrs as $attr) {
            if (strcasecmp($attr['name'], 'name') === 0) {
                $name = substr($raw, $attr['start'], $attr['len']);
            } elseif (strcasecmp($attr['name'], 'value') === 0) {
                $value = $attr;
            }
        }

        if ($name === null || $value === null) {
            return null;
        }

        return [
            'section' => $section,
            'key' => $this->decode($name),
            'value' => $this->decode(substr($raw, $value['start'], $value['len'])),
            'start' => $value['start'],
            'len' => $value['len'],
            'quote' => $value['quote'],
        ];
    }
}
