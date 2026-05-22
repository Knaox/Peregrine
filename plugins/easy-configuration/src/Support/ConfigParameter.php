<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Support;

/**
 * A single parameter read from a config file: its key, its current raw value
 * (as it appears in the file, with surrounding quotes already stripped for
 * quoted formats), and the native section it belongs to (INI/TOML) or null for
 * flat/hierarchical formats (properties/json/yaml use a dotted key instead).
 */
final class ConfigParameter
{
    public function __construct(
        public readonly string $key,
        public readonly string $value,
        public readonly ?string $section = null,
    ) {}

    /** @return array{key: string, value: string, section: string|null} */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'section' => $this->section,
        ];
    }
}
