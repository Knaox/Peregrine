<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Templates;

/**
 * Validates the structure of a template JSON document (a pure render schema).
 * Returns a list of human-readable error strings — empty means valid. Kept
 * dependency-free so it can be unit-tested and reused by the admin editor's
 * live linting.
 */
final class TemplateSchemaValidator
{
    private const FORMATS = ['properties', 'ini', 'yaml', 'json', 'toml', 'palworld', 'theforest', 'xml', 'xml-property'];

    private const DISPLAY_TYPES = ['boolean', 'slider', 'select', 'multiselect', 'text', 'number', 'textarea', 'color'];

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public function validate(array $data): array
    {
        $errors = [];

        $this->requireString($data, 'id', $errors);
        $this->requireString($data, 'version', $errors);
        $this->requireLabel($data, 'name', $errors);

        if (! isset($data['target_eggs']) || ! is_array($data['target_eggs'])) {
            $errors[] = 'target_eggs: must be an array of egg ids';
        } else {
            foreach ($data['target_eggs'] as $eggId) {
                if (! is_int($eggId)) {
                    $errors[] = 'target_eggs: every entry must be an integer egg id';
                    break;
                }
            }
        }

        if (isset($data['boost'])) {
            $this->validateBoost($data['boost'], $errors);
        }

        if (isset($data['columns']) && (! is_int($data['columns']) || $data['columns'] < 1 || $data['columns'] > 3)) {
            $errors[] = 'columns: must be 1, 2 or 3';
        }

        if (isset($data['require_shutdown']) && ! is_bool($data['require_shutdown'])) {
            $errors[] = 'require_shutdown: must be a boolean';
        }

        if (! isset($data['files']) || ! is_array($data['files']) || $data['files'] === []) {
            $errors[] = 'files: at least one file is required';

            return $errors;
        }

        foreach (array_values($data['files']) as $i => $file) {
            $this->validateFile(is_array($file) ? $file : [], "files[{$i}]", $errors);
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $file
     * @param  list<string>  $errors
     */
    private function validateFile(array $file, string $path, array &$errors): void
    {
        $this->requireString($file, 'id', $errors, $path);
        $this->requireString($file, 'path', $errors, $path);

        $format = $file['format'] ?? null;
        if (! is_string($format) || ! in_array($format, self::FORMATS, true)) {
            $errors[] = "{$path}.format: must be one of ".implode(', ', self::FORMATS);
        }

        if (isset($file['section_whitelist']) && ! is_array($file['section_whitelist'])) {
            $errors[] = "{$path}.section_whitelist: must be an array of section names";
        }

        if (isset($file['expanded_by_default']) && ! is_bool($file['expanded_by_default'])) {
            $errors[] = "{$path}.expanded_by_default: must be a boolean";
        }

        if (isset($file['section_expanded'])) {
            if (! is_array($file['section_expanded'])) {
                $errors[] = "{$path}.section_expanded: must be an object of section => boolean";
            } else {
                foreach ($file['section_expanded'] as $sectionKey => $expanded) {
                    if (! is_bool($expanded)) {
                        $errors[] = "{$path}.section_expanded.{$sectionKey}: must be a boolean";
                    }
                }
            }
        }

        if (! isset($file['parameters']) || ! is_array($file['parameters'])) {
            $errors[] = "{$path}.parameters: must be an object";

            return;
        }

        foreach ($file['parameters'] as $key => $value) {
            if (! is_array($value)) {
                $errors[] = "{$path}.parameters.{$key}: must be an object";

                continue;
            }

            // Nested (section -> key -> def) when the value isn't itself a param def.
            if (! isset($value['display_type'])) {
                foreach ($value as $childKey => $childDef) {
                    $this->validateParameter(
                        is_array($childDef) ? $childDef : [],
                        "{$path}.parameters.{$key}.{$childKey}",
                        $errors,
                    );
                }

                continue;
            }

            $this->validateParameter($value, "{$path}.parameters.{$key}", $errors);
        }
    }

    /**
     * @param  array<string, mixed>  $param
     * @param  list<string>  $errors
     */
    private function validateParameter(array $param, string $path, array &$errors): void
    {
        $type = $param['display_type'] ?? null;
        if (! is_string($type) || ! in_array($type, self::DISPLAY_TYPES, true)) {
            $errors[] = "{$path}.display_type: must be one of ".implode(', ', self::DISPLAY_TYPES);
        }

        if (isset($param['config']) && ! is_array($param['config'])) {
            $errors[] = "{$path}.config: must be an object";
        }

        if (isset($param['label']) && ! $this->isLabel($param['label'])) {
            $errors[] = "{$path}.label: must be an object with fr and/or en";
        }
    }

    /**
     * @param  list<string>  $errors
     */
    private function validateBoost(mixed $boost, array &$errors): void
    {
        if (! is_array($boost)) {
            $errors[] = 'boost: must be an object';

            return;
        }
        if (isset($boost['enabled']) && ! is_bool($boost['enabled'])) {
            $errors[] = 'boost.enabled: must be a boolean';
        }
        if (isset($boost['parameter_blacklist']) && ! is_array($boost['parameter_blacklist'])) {
            $errors[] = 'boost.parameter_blacklist: must be an array of parameter keys';
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $errors
     */
    private function requireString(array $data, string $key, array &$errors, string $prefix = ''): void
    {
        $label = $prefix === '' ? $key : "{$prefix}.{$key}";
        if (! isset($data[$key]) || ! is_string($data[$key]) || trim($data[$key]) === '') {
            $errors[] = "{$label}: required, must be a non-empty string";
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $errors
     */
    private function requireLabel(array $data, string $key, array &$errors): void
    {
        if (! isset($data[$key]) || ! $this->isLabel($data[$key])) {
            $errors[] = "{$key}: required, must be an object with fr and/or en";
        }
    }

    private function isLabel(mixed $value): bool
    {
        return is_array($value) && (isset($value['fr']) || isset($value['en']));
    }
}
