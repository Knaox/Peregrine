<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Parsing;

use Plugins\EasyConfiguration\Support\ConfigChange;
use Plugins\EasyConfiguration\Support\ParsedConfig;

/**
 * A config file format handler.
 *
 * Contract — the lossless guarantee:
 *  - `parse()` extracts typed parameters for the UI (read path).
 *  - `apply()` rewrites the file by *surgical value substitution* on the
 *    original raw text (write path): it MUST preserve comments, key ordering,
 *    whitespace, and any line it doesn't touch. Calling `apply()` with an
 *    empty change set MUST return the input byte-for-byte.
 *
 * This split is what makes a true lossless round-trip possible even for YAML
 * and TOML, where no PHP library preserves comments through a dump.
 */
interface ConfigFormat
{
    /** Canonical format id: properties | ini | yaml | json | toml. */
    public function format(): string;

    public function parse(string $raw): ParsedConfig;

    /**
     * @param  list<ConfigChange>  $changes
     */
    public function apply(string $raw, array $changes): string;
}
