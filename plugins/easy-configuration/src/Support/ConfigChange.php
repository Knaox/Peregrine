<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Support;

/**
 * A single value change to apply to a config file. The writer locates the
 * parameter by (section, key) and substitutes only its value token in place,
 * preserving comments, ordering and whitespace. When the key is absent the
 * writer appends it (under its section for native-section formats).
 */
final class ConfigChange
{
    public function __construct(
        public readonly string $key,
        public readonly string $value,
        public readonly ?string $section = null,
        // Which occurrence of a repeated (section, key) to write — 0 for the
        // common single-occurrence case.
        public readonly int $occurrence = 0,
    ) {}
}
