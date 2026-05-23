<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Parsing;

use Plugins\EasyConfiguration\Services\Parsing\Concerns\EditsRawLines;
use Plugins\EasyConfiguration\Support\ConfigParameter;
use Plugins\EasyConfiguration\Support\ParsedConfig;

/**
 * INI files with native `[Section]` headers (ARK GameUserSettings.ini, Palworld,
 * Valheim, …). Comments start with `;` or `#`. Section names are kept verbatim
 * so Unreal-style headers like `[/script/shootergame.shootergamemode]` survive.
 *
 * Keys before any header have a null section. A changed value is substituted in
 * place; a missing key is inserted at the end of its section's block (a new
 * section is appended at EOF). First-seen duplicate key wins for editing.
 */
final class IniFormat implements ConfigFormat
{
    use EditsRawLines;

    public function format(): string
    {
        return 'ini';
    }

    public function parse(string $raw): ParsedConfig
    {
        $parameters = [];
        $section = null;
        $counts = []; // "section\x1fkey" => occurrences seen so far

        foreach ($this->splitLines($this->stripBom($raw)) as $chunk) {
            $body = $this->bodyOf($chunk);
            $header = $this->sectionHeader($body);
            if ($header !== null) {
                $section = $header;

                continue;
            }

            $eq = $this->keyEquals($body);
            if ($eq === null) {
                continue;
            }

            $key = trim(substr($body, 0, $eq));
            $id = ($section ?? '')."\x1f".$key;
            $occurrence = $counts[$id] ?? 0;
            $counts[$id] = $occurrence + 1;
            $parameters[] = new ConfigParameter($key, trim(substr($body, $eq + 1)), $section, $occurrence);
        }

        return new ParsedConfig($parameters);
    }

    public function apply(string $raw, array $changes): string
    {
        if ($changes === []) {
            return $raw;
        }

        /** @var array<string, array<string, array<int, string>>> $pending sectionKey => key => [occurrence => value] */
        $pending = [];
        foreach ($changes as $change) {
            $pending[$change->section ?? ''][$change->key][$change->occurrence] = $change->value;
        }

        $eol = $this->dominantEol($raw);
        $section = null;
        $seen = []; // sectionKey => key => occurrences walked so far
        $out = [];

        foreach ($this->splitLines($raw) as $chunk) {
            $body = $this->bodyOf($chunk);
            $lineEol = $this->eolOf($chunk);

            $header = $this->sectionHeader($body);
            if ($header !== null) {
                $this->flushSection($out, $pending, $section, $eol);
                $section = $header;
                $out[] = $chunk;

                continue;
            }

            $eq = $this->keyEquals($body);
            $sk = $section ?? '';
            if ($eq !== null) {
                $key = trim(substr($body, 0, $eq));
                $occurrence = $seen[$sk][$key] ?? 0;
                $seen[$sk][$key] = $occurrence + 1;
                if (isset($pending[$sk][$key][$occurrence])) {
                    $head = substr($body, 0, $eq + 1);
                    $rest = substr($body, $eq + 1);
                    $lead = substr($rest, 0, strlen($rest) - strlen(ltrim($rest)));
                    $out[] = $head.$lead.$pending[$sk][$key][$occurrence].$lineEol;
                    unset($pending[$sk][$key][$occurrence]);
                    if ($pending[$sk][$key] === []) {
                        unset($pending[$sk][$key]);
                    }

                    continue;
                }
            }

            $out[] = $chunk;
        }

        $this->flushSection($out, $pending, $section, $eol);
        $result = implode('', $out);

        // Sections that never appeared in the file: append fresh blocks.
        foreach ($pending as $sk => $entries) {
            if ($entries === []) {
                continue;
            }
            if ($sk !== '') {
                $result = $this->appendLine($result, '['.$sk.']', $eol);
            }
            foreach ($entries as $key => $occValues) {
                foreach ($occValues as $value) {
                    $result = $this->appendLine($result, $key.'='.$value, $eol);
                }
            }
        }

        return $result;
    }

    /**
     * Inject any not-yet-placed keys for $section at the end of its block
     * (right before we move on to the next header / EOF), then clear them.
     *
     * @param  list<string>  $out
     * @param  array<string, array<string, array<int, string>>>  $pending
     */
    private function flushSection(array &$out, array &$pending, ?string $section, string $eol): void
    {
        $sk = $section ?? '';
        if (empty($pending[$sk])) {
            return;
        }

        $this->ensureTrailingEol($out, $eol);
        foreach ($pending[$sk] as $key => $occValues) {
            foreach ($occValues as $value) {
                $out[] = $key.'='.$value.$eol;
            }
        }
        $pending[$sk] = [];
    }

    private function sectionHeader(string $body): ?string
    {
        if (preg_match('/^\s*\[(.+?)\]\s*$/', $body, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /** Index of the `=` on a settable key line; null for comments/blanks/headers. */
    private function keyEquals(string $body): ?int
    {
        $start = ltrim($body);
        if ($start === '' || $start[0] === ';' || $start[0] === '#' || $start[0] === '[') {
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
