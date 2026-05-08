<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Java Compatibility — Plugin Defaults
|--------------------------------------------------------------------------
|
| Single source of truth for the Minecraft → Java mapping and the Docker
| images used by the installer. **Nothing is hardcoded in PHP source code**;
| if you need to add a new Minecraft version, swap a Docker registry, or
| pin a specific yolk image, this file is the place.
|
| These values can also be overridden at runtime via the Filament admin
| page (see ModpackConfigResource → "Compatibilité Java"). The DB override
| wins per-key for images, and replaces the whole list for rules.
|
| Sources cross-checked against:
|   - Mojang server release notes (java versions per minecraft release)
|   - Forge changelog (1.16.5 strictly Java 8 ; 1.20.5 jumps to 21)
|   - NeoForge release notes (forks at 1.20.1)
|   - Fabric / Quilt loader notes (track Vanilla minimums)
|
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Default Java major
    |--------------------------------------------------------------------------
    |
    | Used when the matrix cannot resolve a rule (unknown MC version, exotic
    | loader, manifest with no metadata). 17 is the safest middle ground in
    | 2026 — it covers everything from 1.18 through 1.20.4.
    */
    'default_java' => 17,

    /*
    |--------------------------------------------------------------------------
    | Compatibility rules
    |--------------------------------------------------------------------------
    |
    | Each rule is evaluated TOP-DOWN; the first match wins. A rule matches
    | when:
    |   - rule.loader matches the modpack loader (or rule.loader is null,
    |     which acts as a wildcard for any loader incl. vanilla)
    |   - rule.mc_min ≤ modpack mc version ≤ rule.mc_max (PHP version_compare,
    |     bounds inclusive ; null bound = open-ended)
    |
    | Loader-specific rules MUST come before the generic (loader=null) ones,
    | otherwise the wildcard rule eats them.
    */
    'rules' => [

        // ── Forge ──────────────────────────────────────────────────────────
        // Forge keeps Java 8 strictly through 1.16.5 (refuses Java 9+).
        ['loader' => 'forge',    'mc_min' => null,     'mc_max' => '1.16.5', 'java' => 8],
        ['loader' => 'forge',    'mc_min' => '1.17',   'mc_max' => '1.17.1', 'java' => 16],
        ['loader' => 'forge',    'mc_min' => '1.18',   'mc_max' => '1.20.4', 'java' => 17],
        ['loader' => 'forge',    'mc_min' => '1.20.5', 'mc_max' => null,     'java' => 21],

        // ── NeoForge ───────────────────────────────────────────────────────
        // NeoForge forked from Forge at 1.20.1. No earlier versions exist.
        ['loader' => 'neoforge', 'mc_min' => '1.20.1', 'mc_max' => '1.20.4', 'java' => 17],
        ['loader' => 'neoforge', 'mc_min' => '1.20.5', 'mc_max' => null,     'java' => 21],

        // ── Fabric ─────────────────────────────────────────────────────────
        // Tracks Vanilla minimums.
        ['loader' => 'fabric',   'mc_min' => null,     'mc_max' => '1.16.5', 'java' => 8],
        ['loader' => 'fabric',   'mc_min' => '1.17',   'mc_max' => '1.17.1', 'java' => 16],
        ['loader' => 'fabric',   'mc_min' => '1.18',   'mc_max' => '1.20.4', 'java' => 17],
        ['loader' => 'fabric',   'mc_min' => '1.20.5', 'mc_max' => null,     'java' => 21],

        // ── Quilt ──────────────────────────────────────────────────────────
        // Quilt tracks Fabric one-for-one for runtime requirements.
        ['loader' => 'quilt',    'mc_min' => null,     'mc_max' => '1.16.5', 'java' => 8],
        ['loader' => 'quilt',    'mc_min' => '1.17',   'mc_max' => '1.17.1', 'java' => 16],
        ['loader' => 'quilt',    'mc_min' => '1.18',   'mc_max' => '1.20.4', 'java' => 17],
        ['loader' => 'quilt',    'mc_min' => '1.20.5', 'mc_max' => null,     'java' => 21],

        // ── Vanilla / no loader / unknown ──────────────────────────────────
        // Mojang official minimums.
        ['loader' => null,       'mc_min' => null,     'mc_max' => '1.16.5', 'java' => 8],
        ['loader' => null,       'mc_min' => '1.17',   'mc_max' => '1.17.1', 'java' => 16],
        ['loader' => null,       'mc_min' => '1.18',   'mc_max' => '1.20.4', 'java' => 17],
        ['loader' => null,       'mc_min' => '1.20.5', 'mc_max' => null,     'java' => 21],
    ],

    /*
    |--------------------------------------------------------------------------
    | Docker images per Java major
    |--------------------------------------------------------------------------
    |
    | The installer (and the post-install swap-back) pick an image from this
    | map based on the resolved Java version. Keys MUST be strings ("8" not
    | 8) — JSON casting in the override column round-trips them as strings,
    | and PHP keeps insertion order on string keys.
    |
    | Override individual keys via the Filament admin page (e.g. point only
    | java_21 at a private mirror) without re-declaring the rest.
    */
    'images' => [
        '8'  => 'ghcr.io/pelican-eggs/yolks:java_8',
        '11' => 'ghcr.io/pelican-eggs/yolks:java_11',
        '16' => 'ghcr.io/pelican-eggs/yolks:java_17', // No java_16 yolk; 17 is BC for MC 1.17.
        '17' => 'ghcr.io/pelican-eggs/yolks:java_17',
        '21' => 'ghcr.io/pelican-eggs/yolks:java_21',
    ],
];
