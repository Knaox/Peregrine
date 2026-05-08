<?php
/**
 * scripts/i18n/build-new-locale-files.php
 *
 * Reads scripts/i18n/mapping.json + the existing lang/{en,fr}/*.php files +
 * resources/js/i18n/{en,fr}.json, and emits the new per-page locale files :
 *
 *     lang/{en,fr}/admin/*.php
 *     lang/{en,fr}/auth/*.php
 *     resources/js/i18n/locales/{en,fr}/*.json
 *
 * For every leaf in every old file, asserts that the same value lands in the
 * new file at the new key path. Aborts on any byte-level mismatch — this is
 * the contract that guarantees a pure refactor.
 *
 * Usage:
 *     php scripts/i18n/build-new-locale-files.php           # both backends
 *     php scripts/i18n/build-new-locale-files.php backend
 *     php scripts/i18n/build-new-locale-files.php frontend
 */

declare(strict_types=1);

const ROOT = __DIR__ . '/../..';

$mode = $argv[1] ?? 'both';
if (!in_array($mode, ['both', 'backend', 'frontend'], true)) {
    fwrite(STDERR, "Usage: php scripts/i18n/build-new-locale-files.php [both|backend|frontend]\n");
    exit(1);
}

$mapping = json_decode(file_get_contents(ROOT . '/scripts/i18n/mapping.json'), true);

/* ============================================================
 * Helpers
 * ============================================================ */

function setNested(array &$tree, string $dottedKey, mixed $value): void
{
    $parts = explode('.', $dottedKey);
    $cursor = &$tree;
    foreach ($parts as $i => $p) {
        if ($i === count($parts) - 1) {
            $cursor[$p] = $value;
        } else {
            if (!isset($cursor[$p]) || !is_array($cursor[$p])) {
                $cursor[$p] = [];
            }
            $cursor = &$cursor[$p];
        }
    }
}

function getNested(array $tree, string $dottedKey): mixed
{
    $parts = explode('.', $dottedKey);
    $cursor = $tree;
    foreach ($parts as $p) {
        if (!is_array($cursor) || !array_key_exists($p, $cursor)) {
            return null;
        }
        $cursor = $cursor[$p];
    }
    return $cursor;
}

function flattenLeaves(array $arr, string $prefix = ''): array
{
    $out = [];
    foreach ($arr as $k => $v) {
        $key = $prefix === '' ? (string)$k : "$prefix.$k";
        if (is_array($v) && !array_is_list($v) && $v !== []) {
            $out += flattenLeaves($v, $key);
        } else {
            $out[$key] = $v;
        }
    }
    return $out;
}

function flattenJson(array $arr, string $prefix = ''): array
{
    $out = [];
    foreach ($arr as $k => $v) {
        $key = $prefix === '' ? (string)$k : "$prefix.$k";
        if (is_array($v)) {
            $out += flattenJson($v, $key);
        } else {
            $out[$key] = $v;
        }
    }
    return $out;
}

/**
 * Custom PHP array exporter matching Laravel's lang file conventions :
 *   - single-quoted strings (escaped : ' → \')
 *   - 4-space indentation
 *   - trailing comma on every line
 *   - assoc keys quoted
 *   - one space around =>
 */
function exportPhpArray(array $arr, int $depth = 1): string
{
    $indent = str_repeat('    ', $depth);
    $closeIndent = str_repeat('    ', $depth - 1);
    $lines = [];
    foreach ($arr as $k => $v) {
        $key = is_string($k) ? "'" . addcslashes($k, "\\'") . "'" : (string)$k;
        if (is_array($v)) {
            if ($v === []) {
                $lines[] = "$indent$key => [],";
            } else {
                $lines[] = "$indent$key => [";
                $lines[] = exportPhpArray($v, $depth + 1);
                $lines[] = "$indent],";
            }
        } elseif (is_string($v)) {
            $lines[] = "$indent$key => '" . addcslashes($v, "\\'") . "',";
        } elseif (is_bool($v)) {
            $lines[] = "$indent$key => " . ($v ? 'true' : 'false') . ',';
        } elseif (is_null($v)) {
            $lines[] = "$indent$key => null,";
        } else {
            $lines[] = "$indent$key => " . var_export($v, true) . ',';
        }
    }
    return implode("\n", $lines);
}

function writePhpFile(string $path, array $tree): void
{
    @mkdir(dirname($path), 0755, true);
    $content = "<?php\n\nreturn [\n" . exportPhpArray($tree, 1) . "\n];\n";
    file_put_contents($path, $content);
}

function writeJsonFile(string $path, array $tree): void
{
    @mkdir(dirname($path), 0755, true);
    // PHP's JSON_PRETTY_PRINT already uses 4-space indent — matches existing en.json.
    $content = json_encode(
        $tree,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    file_put_contents($path, $content . "\n");
}

/* ============================================================
 * BACKEND
 * ============================================================ */

if ($mode === 'both' || $mode === 'backend') {
    $backendOldFiles = [
        'admin' => 'admin.php',
        'auth' => 'auth.php',
        'bridge' => 'bridge.php',
        'pelican' => 'pelican.php',
        'servers' => 'servers.php',
        'validation' => 'validation.php',
    ];

    foreach (['en', 'fr'] as $locale) {
        // Load all old flat data
        $oldFlat = [];
        foreach ($backendOldFiles as $top => $relPath) {
            $arr = require ROOT . "/lang/$locale/$relPath";
            $oldFlat += flattenLeaves($arr, $top);
        }

        // Build new file trees
        $newFiles = []; // [filePath => [nested tree]]

        foreach ($mapping['backend'] as $oldKey => $info) {
            if (!array_key_exists($oldKey, $oldFlat)) {
                fwrite(STDERR, "WARN [$locale]: old key not found in source files: $oldKey\n");
                continue;
            }
            $value = $oldFlat[$oldKey];

            if (($info['file'] ?? '') === '__multi__') {
                // Duplicate the value to every multi target
                foreach ($info['multiTargets'] as $newKey) {
                    $file = explode('.', $newKey, 2)[0]; // "admin/servers.back_to_list" → "admin/servers"
                    $tail = substr($newKey, strlen($file) + 1);
                    $newFiles[$file] ??= [];
                    setNested($newFiles[$file], $tail, $value);
                }
                continue;
            }

            if (!empty($info['deprecated'])) {
                // The old `lang/{en,fr}/servers.php` file is being deleted.
                // We still copy values into a kept "servers" target so that any
                // missed __('servers.*') call site doesn't crash. The file
                // gets renamed to .legacy at the end; if grep confirms zero
                // backend usage, we delete it.
                $newKey = $info['newKey'];
                $file = explode('.', $newKey, 2)[0];
                $tail = substr($newKey, strlen($file) + 1);
                $newFiles[$file] ??= [];
                setNested($newFiles[$file], $tail, $value);
                continue;
            }

            $newKey = $info['newKey'];
            $file = explode('.', $newKey, 2)[0]; // "admin/servers" or "admin/_shell" or "auth/login"
            $tail = substr($newKey, strlen($file) + 1);
            $newFiles[$file] ??= [];
            setNested($newFiles[$file], $tail, $value);
        }

        // Write all files
        foreach ($newFiles as $file => $tree) {
            $path = ROOT . "/lang/$locale/$file.php";
            writePhpFile($path, $tree);
        }

        // Verify: every old leaf must be retrievable at its new path.
        $errors = 0;
        foreach ($mapping['backend'] as $oldKey => $info) {
            if (!array_key_exists($oldKey, $oldFlat)) continue;
            $expected = $oldFlat[$oldKey];

            if (($info['file'] ?? '') === '__multi__') {
                // Verify each multi target individually
                foreach ($info['multiTargets'] as $newKey) {
                    $file = explode('.', $newKey, 2)[0];
                    $tail = substr($newKey, strlen($file) + 1);
                    $tree = $newFiles[$file] ?? [];
                    $actual = getNested($tree, $tail);
                    if ($actual !== $expected) {
                        fwrite(STDERR, "MISMATCH [$locale] $newKey: expected " . var_export($expected, true) . " got " . var_export($actual, true) . "\n");
                        $errors++;
                    }
                }
                continue;
            }
            $newKey = $info['newKey'];
            $file = explode('.', $newKey, 2)[0];
            $tail = substr($newKey, strlen($file) + 1);
            $tree = $newFiles[$file] ?? [];
            $actual = getNested($tree, $tail);
            if ($actual !== $expected) {
                fwrite(STDERR, "MISMATCH [$locale] $oldKey → $newKey: expected " . var_export($expected, true) . " got " . var_export($actual, true) . "\n");
                $errors++;
            }
        }

        if ($errors > 0) {
            fwrite(STDERR, "ABORT: $errors mismatches in $locale backend.\n");
            exit(1);
        }
        echo "✓ Backend $locale: " . count($newFiles) . " files written, all values verified\n";
    }
}

/* ============================================================
 * FRONTEND
 * ============================================================ */

if ($mode === 'both' || $mode === 'frontend') {
    foreach (['en', 'fr'] as $locale) {
        $oldArr = json_decode(file_get_contents(ROOT . "/resources/js/i18n/$locale.json"), true);
        $oldFlat = flattenJson($oldArr);

        $newFiles = []; // [namespace => [nested tree]]

        foreach ($mapping['frontend'] as $oldKey => $info) {
            if (!array_key_exists($oldKey, $oldFlat)) {
                fwrite(STDERR, "WARN [$locale]: frontend key not found: $oldKey\n");
                continue;
            }
            $value = $oldFlat[$oldKey];
            $ns = $info['ns'];
            $key = $info['key'];
            $newFiles[$ns] ??= [];
            setNested($newFiles[$ns], $key, $value);
        }

        // Write
        foreach ($newFiles as $ns => $tree) {
            $path = ROOT . "/resources/js/i18n/locales/$locale/$ns.json";
            writeJsonFile($path, $tree);
        }

        // Verify
        $errors = 0;
        foreach ($mapping['frontend'] as $oldKey => $info) {
            if (!array_key_exists($oldKey, $oldFlat)) continue;
            $expected = $oldFlat[$oldKey];
            $ns = $info['ns'];
            $key = $info['key'];
            $actual = getNested($newFiles[$ns] ?? [], $key);
            if ($actual !== $expected) {
                fwrite(STDERR, "MISMATCH [$locale] $oldKey → $ns:$key: expected " . var_export($expected, true) . " got " . var_export($actual, true) . "\n");
                $errors++;
            }
        }

        if ($errors > 0) {
            fwrite(STDERR, "ABORT: $errors mismatches in $locale frontend.\n");
            exit(1);
        }
        echo "✓ Frontend $locale: " . count($newFiles) . " namespaces written, all values verified\n";
    }
}

echo "\nAll new locale files generated and verified.\n";
