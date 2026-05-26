<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Config;

use Plugins\EasyConfiguration\Services\Parsing\TypeDetector;
use Plugins\EasyConfiguration\Support\ParsedConfig;

/**
 * Merges a template file's render schema with the live values parsed from the
 * real config file. Template-declared parameters come first (curated order),
 * followed by any extra keys found in the file but absent from the template
 * (auto-detected). `section_whitelist` filters native sections for INI/TOML.
 *
 * Pure — no IO — so the merge contract is fully unit-tested.
 */
final class ConfigMerger
{
    public function __construct(private readonly TypeDetector $detector) {}

    /**
     * @param  array<string, mixed>  $fileDef  a template "file" entry
     * @return array{sectioned: bool, parameters: list<array<string, mixed>>}
     */
    public function merge(array $fileDef, ParsedConfig $parsed): array
    {
        $format = (string) ($fileDef['format'] ?? '');
        $sectioned = in_array($format, ['ini', 'toml', 'xml-property'], true);
        $whitelist = is_array($fileDef['section_whitelist'] ?? null) ? $fileDef['section_whitelist'] : [];

        $parameters = [];
        $claimed = [];
        $templateDefs = [];

        foreach ($this->flattenTemplate($fileDef['parameters'] ?? []) as $entry) {
            ['section' => $section, 'key' => $key, 'def' => $def] = $entry;
            if ($this->filtered($sectioned, $whitelist, $section)) {
                continue;
            }

            $found = $parsed->get($key, $section);
            $parameters[] = $this->build($key, $section, $def, $found?->value ?? $this->defaultValue($def), false, 0);
            // A template maps to the FIRST occurrence (claim occ 0 only); extra
            // occurrences of a repeated key are surfaced below, reusing this def.
            $claimed[$this->compositeKey($section, $key)."\x1f0"] = true;
            $templateDefs[$this->compositeKey($section, $key)] = $def;
        }

        foreach ($parsed->parameters as $param) {
            $composite = $this->compositeKey($param->section, $param->key);
            if (isset($claimed[$composite."\x1f".$param->occurrence])) {
                continue;
            }
            if ($this->filtered($sectioned, $whitelist, $param->section)) {
                continue;
            }

            // Reuse the template definition for additional occurrences of a
            // templated key (same control + label); otherwise auto-detect.
            $tplDef = $templateDefs[$composite] ?? null;
            $parameters[] = $this->build(
                $param->key,
                $param->section,
                $tplDef ?? ['display_type' => $this->detector->detect($param->value)],
                $param->value,
                $tplDef === null,
                $param->occurrence,
            );
        }

        return ['sectioned' => $sectioned, 'parameters' => $parameters];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<array{section: string|null, key: string, def: array<string, mixed>}>
     */
    private function flattenTemplate(array $params): array
    {
        $out = [];
        foreach ($params as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if (isset($value['display_type'])) {
                $out[] = ['section' => null, 'key' => (string) $key, 'def' => $value];

                continue;
            }

            foreach ($value as $childKey => $childDef) {
                if (is_array($childDef)) {
                    $out[] = ['section' => (string) $key, 'key' => (string) $childKey, 'def' => $childDef];
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $def
     * @return array<string, mixed>
     */
    private function build(string $key, ?string $section, array $def, string $value, bool $inferred, int $occurrence): array
    {
        return [
            'key' => $key,
            'section' => $section,
            'display_type' => (string) ($def['display_type'] ?? 'text'),
            'config' => is_array($def['config'] ?? null) ? $def['config'] : new \stdClass,
            'label' => $def['label'] ?? null,
            'description' => $def['description'] ?? null,
            'value' => $value,
            'inferred' => $inferred,
            'occurrence' => $occurrence,
            'env_var' => isset($def['env_var']) && $def['env_var'] !== '' ? (string) $def['env_var'] : null,
        ];
    }

    /** @param array<string, mixed> $def */
    private function defaultValue(array $def): string
    {
        $default = $def['config']['default'] ?? null;
        if (is_bool($default)) {
            return $default ? 'true' : 'false';
        }

        return $default === null ? '' : (string) $default;
    }

    /** @param list<mixed> $whitelist */
    private function filtered(bool $sectioned, array $whitelist, ?string $section): bool
    {
        return $sectioned && $whitelist !== [] && $section !== null && ! in_array($section, $whitelist, true);
    }

    private function compositeKey(?string $section, string $key): string
    {
        return ($section ?? '')."\x1f".$key;
    }
}
