<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Parsing;

/**
 * Infers a display type for a parameter that the template doesn't describe
 * (auto-detection). Deliberately conservative — matching the spec:
 *   `true`/`false` (case-insensitive) -> boolean
 *   a numeric string                  -> number
 *   anything else                     -> text
 *
 * Ambiguous toggle spellings (`yes/no`, `1/0`, `on/off`) are NOT auto-promoted
 * to boolean: a template must opt them in explicitly via a `boolean` field with
 * custom `true_value`/`false_value`, otherwise `1`/`0` read as numbers.
 */
final class TypeDetector
{
    public function detect(string $value): string
    {
        $trimmed = trim($value);
        $lower = strtolower($trimmed);

        if ($lower === 'true' || $lower === 'false') {
            return 'boolean';
        }

        if ($trimmed !== '' && is_numeric($trimmed)) {
            return 'number';
        }

        return 'text';
    }
}
