<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Import;

use Plugins\EasyConfiguration\Services\Parsing\TypeDetector;
use Plugins\EasyConfiguration\Support\ParsedConfig;

/**
 * Turns a parsed config file into a ready-to-edit template `file` block: every
 * key becomes a parameter pre-filled with the value read from the server (its
 * `config.default`) and a guessed `display_type`. Sectioned formats (ini/toml)
 * nest parameters under their section; flat/hierarchical formats keep the dotted
 * key at the top level — matching the template schema exactly so the admin can
 * tweak display types + add FR/EN labels, then save without touching raw JSON.
 *
 * No values are persisted in the template: the imported value is only a default
 * the editor/preview falls back to when the live file omits the key.
 */
final class ConfigImportScaffolder
{
    /** path extension (lowercased) → parser format id */
    private const EXTENSION_FORMATS = [
        'properties' => 'properties',
        'ini' => 'ini',
        'cfg' => 'ini',
        'conf' => 'ini',
        'toml' => 'toml',
        'yml' => 'yaml',
        'yaml' => 'yaml',
        'json' => 'json',
        'xml' => 'xml',
    ];

    public function __construct(private readonly TypeDetector $detector) {}

    /**
     * Best-effort format from a file path's extension. Returns null when the
     * extension is unknown so the caller can ask the admin to pick one.
     */
    public static function detectFormat(string $path): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return self::EXTENSION_FORMATS[$extension] ?? null;
    }

    /**
     * A `.xml` extension alone can't tell apart a generic XML file from a
     * `<property name="K" value="V"/>` list (7 Days to Die's `serverconfig.xml`
     * and friends). When the content clearly uses that idiom, the dedicated
     * `xml-property` format is the right pick — one editable field per setting,
     * instead of every name/value attribute surfaced twice.
     */
    public static function looksLikePropertyXml(string $raw): bool
    {
        return preg_match('/<property\s[^>]*\bname\s*=\s*["\'][^"\']*["\'][^>]*\bvalue\s*=/i', $raw) === 1;
    }

    /**
     * @return array{id: string, path: string, format: string, enabled: bool, label: array<string, string>, parameters: array<string, mixed>}
     */
    public function scaffold(string $path, string $format, ParsedConfig $parsed): array
    {
        $parameters = [];

        foreach ($parsed->parameters as $param) {
            $built = $this->buildParameter($param->value);
            $section = $param->section;

            if ($section !== null && $section !== '') {
                $parameters[$section] ??= [];
                $parameters[$section][$param->key] = $built;
            } else {
                $parameters[$param->key] = $built;
            }
        }

        return [
            'id' => $this->fileId($path),
            'path' => $path,
            'format' => $format,
            'enabled' => true,
            'label' => ['en' => $this->humanize($path)],
            'parameters' => $parameters,
        ];
    }

    /**
     * @return array{display_type: string, config: array<string, mixed>}
     */
    private function buildParameter(string $value): array
    {
        $type = $this->detector->detect($value);
        $config = ['default' => $value];

        if ($type === 'boolean') {
            $config['true_value'] = 'true';
            $config['false_value'] = 'false';
        }

        return [
            'display_type' => $type,
            'config' => $config,
        ];
    }

    /** Slug derived from the file name, used as the template `file.id`. */
    private function fileId(string $path): string
    {
        $base = strtolower(basename($path));
        $slug = (string) preg_replace('/[^a-z0-9._-]+/', '-', $base);
        $slug = trim((string) preg_replace('/-+/', '-', str_replace(['.', '_'], '-', $slug)), '-');

        return $slug === '' ? 'imported-file' : $slug;
    }

    /** Human-friendly default EN label, e.g. `bukkit.yml` → `Bukkit`. */
    private function humanize(string $path): string
    {
        $name = pathinfo($path, PATHINFO_FILENAME);
        $name = trim((string) preg_replace('/[._-]+/', ' ', $name));

        return $name === '' ? basename($path) : ucwords($name);
    }
}
