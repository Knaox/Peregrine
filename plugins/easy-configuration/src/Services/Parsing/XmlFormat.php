<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Parsing;

use Plugins\EasyConfiguration\Services\Parsing\Concerns\ScansXml;
use Plugins\EasyConfiguration\Support\ConfigParameter;
use Plugins\EasyConfiguration\Support\ParsedConfig;

/**
 * XML config files (some game servers, mod loaders and dedicated tools ship
 * settings as XML). Values are exposed two ways, both as flat dotted keys
 * (section = null), mirroring the JSON/YAML model:
 *   - element text:  `<server><port>25565</port></server>` -> key `server.port`
 *   - attribute:     `<slot name="x" value="5"/>`          -> keys `slot@name`, `slot@value`
 *
 * "Lossless" means surgical substitution: `apply()` rewrites only the matched
 * value span (text or quoted attribute), preserving comments, attribute order,
 * the original quote style, indentation and every untouched byte — an empty
 * change set returns the input verbatim. A key that is absent from the file is
 * skipped (XML has no unambiguous insertion point), the same conservative stance
 * YAML takes for deep keys.
 */
final class XmlFormat implements ConfigFormat
{
    use ScansXml;

    public function format(): string
    {
        return 'xml';
    }

    public function parse(string $raw): ParsedConfig
    {
        $occurrences = [];
        $parameters = [];

        foreach ($this->scanSlots($raw) as $slot) {
            $n = $occurrences[$slot['key']] ?? 0;
            $occurrences[$slot['key']] = $n + 1;
            $parameters[] = new ConfigParameter($slot['key'], $slot['value'], null, $n);
        }

        return new ParsedConfig($parameters);
    }

    public function apply(string $raw, array $changes): string
    {
        if ($changes === []) {
            return $raw;
        }

        $slots = $this->scanSlots($raw);

        // Group slot indices by key, in document order, so the Nth occurrence of
        // a repeated path maps to the Nth slot — matching parse()'s numbering.
        $byKey = [];
        foreach ($slots as $index => $slot) {
            $byKey[$slot['key']][] = $index;
        }

        $edits = [];
        foreach ($changes as $change) {
            $index = $byKey[$change->key][$change->occurrence] ?? null;
            if ($index === null) {
                continue;
            }
            $slot = $slots[$index];
            $edits[] = [
                'start' => $slot['start'],
                'len' => $slot['len'],
                'replacement' => $slot['attr']
                    ? $this->escapeAttr($change->value, $slot['quote'])
                    : $this->escapeText($change->value),
            ];
        }

        // Apply right-to-left so earlier offsets stay valid as the string grows
        // or shrinks.
        usort($edits, static fn (array $a, array $b): int => $b['start'] <=> $a['start']);
        foreach ($edits as $edit) {
            $raw = substr($raw, 0, $edit['start']).$edit['replacement'].substr($raw, $edit['start'] + $edit['len']);
        }

        return $raw;
    }
}
