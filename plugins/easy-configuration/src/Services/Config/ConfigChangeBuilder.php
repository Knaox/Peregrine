<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Config;

use Plugins\EasyConfiguration\Support\ConfigChange;

/**
 * Turns the values submitted for one file into validated ConfigChange objects.
 * Template-declared parameters are validated against their definition; extra
 * (auto-detected) keys are accepted as free text. Errors are keyed by
 * "section\x1fkey" so the API can point the UI at the offending field.
 */
final class ConfigChangeBuilder
{
    public function __construct(private readonly ConfigValueValidator $validator) {}

    /**
     * @param  array<string, mixed>  $fileDef
     * @param  list<array{key: string, section?: string|null, value: string, occurrence?: int}>  $values
     * @return array{changes: list<ConfigChange>, errors: array<string, string>}
     */
    public function build(array $fileDef, array $values): array
    {
        $defs = $this->indexDefs($fileDef['parameters'] ?? []);
        $changes = [];
        $errors = [];

        foreach ($values as $value) {
            $key = (string) ($value['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $section = isset($value['section']) && is_string($value['section']) ? $value['section'] : null;
            $raw = (string) ($value['value'] ?? '');
            $occurrence = isset($value['occurrence']) && is_numeric($value['occurrence']) ? (int) $value['occurrence'] : 0;
            $composite = ($section ?? '')."\x1f".$key;

            $def = $defs[$composite] ?? null;
            if ($def !== null) {
                $error = $this->validator->validate($def, $raw);
                if ($error !== null) {
                    $errors[$composite] = $error;

                    continue;
                }
            }

            $changes[] = new ConfigChange($key, $raw, $section, $occurrence);
        }

        return ['changes' => $changes, 'errors' => $errors];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, array<string, mixed>>
     */
    private function indexDefs(array $params): array
    {
        $out = [];
        foreach ($params as $key => $value) {
            if (! is_array($value)) {
                continue;
            }
            if (isset($value['display_type'])) {
                $out["\x1f".$key] = $value;

                continue;
            }
            foreach ($value as $childKey => $childDef) {
                if (is_array($childDef)) {
                    $out[$key."\x1f".$childKey] = $childDef;
                }
            }
        }

        return $out;
    }
}
