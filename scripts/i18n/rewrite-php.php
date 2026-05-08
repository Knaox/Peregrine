<?php
/**
 * scripts/i18n/rewrite-php.php
 *
 * Mechanically rewrites every `__('OLD')`, `trans('OLD')`, `Lang::get('OLD')`
 * literal in the codebase to its new key path, driven by
 * `scripts/i18n/mapping.json`.
 *
 * Scope:
 *   - app/**.php
 *   - resources/views/**.blade.php
 *   - routes/**.php
 *   - database/{seeders,factories}/**.php
 *   - config/**.php (defensive — usually no __() calls)
 *
 * Excluded: `plugins/**` (explicitly out of scope), `vendor/**`, `tests/**`
 * (rewritten on demand if any test asserts a specific old key).
 *
 * Idempotent: target keys never appear in the mapping's source side, so a
 * second run is a no-op.
 *
 * Multi-target keys (e.g. admin.resource_pages.back_to_list) are resolved
 * per file via the `byFile` regex map embedded in the mapping. Files not
 * matching any pattern are reported and left untouched (manual review).
 *
 * Usage:
 *     php scripts/i18n/rewrite-php.php           # apply rewrites
 *     php scripts/i18n/rewrite-php.php --dry-run # show diff stats only
 */

declare(strict_types=1);

const ROOT = __DIR__ . '/../..';

$dryRun = in_array('--dry-run', $argv, true);
$mapping = json_decode(file_get_contents(ROOT . '/scripts/i18n/mapping.json'), true)['backend'];

$globPatterns = [
    'app/**/*.php',
    'resources/views/**/*.blade.php',
    'routes/**/*.php',
    'database/seeders/**/*.php',
    'database/factories/**/*.php',
    'config/**/*.php',
];

function rglob(string $root, string $pattern): array
{
    $out = [];
    $segments = explode('/', $pattern);
    $iterate = function (string $dir, array $segs) use (&$iterate, &$out) {
        if (!$segs) { return; }
        $head = array_shift($segs);
        if ($head === '**') {
            // Match zero or more directories
            if ($segs) {
                $iterate($dir, $segs); // zero
                if (is_dir($dir)) {
                    foreach (scandir($dir) ?: [] as $entry) {
                        if ($entry === '.' || $entry === '..') continue;
                        $full = "$dir/$entry";
                        if (is_dir($full)) $iterate($full, ['**', ...$segs]);
                    }
                }
            }
            return;
        }
        if (str_contains($head, '*')) {
            $regex = '#^' . str_replace('\*', '[^/]*', preg_quote($head, '#')) . '$#';
            if (!is_dir($dir)) return;
            foreach (scandir($dir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                if (preg_match($regex, $entry)) {
                    $full = "$dir/$entry";
                    if ($segs) {
                        if (is_dir($full)) $iterate($full, $segs);
                    } else {
                        if (is_file($full)) $out[] = $full;
                    }
                }
            }
            return;
        }
        $full = "$dir/$head";
        if ($segs) {
            if (is_dir($full)) $iterate($full, $segs);
        } else {
            if (is_file($full)) $out[] = $full;
        }
    };
    $iterate($root, $segments);
    return $out;
}

$allFiles = [];
foreach ($globPatterns as $pattern) {
    foreach (rglob(ROOT, $pattern) as $f) $allFiles[$f] = true;
}
$allFiles = array_keys($allFiles);
sort($allFiles);

echo "Scanning " . count($allFiles) . " files…\n";

$rewriteCount = 0;
$fileCount = 0;
$multiUnresolved = [];

foreach ($allFiles as $file) {
    $content = file_get_contents($file);
    $orig = $content;
    $rel = ltrim(str_replace(ROOT, '', $file), '/');

    // Match __('KEY'), __("KEY"), trans('KEY'), trans("KEY"),
    // Lang::get('KEY'), Lang::get("KEY"), and the short __('KEY', […]) form
    // which is identical for our purposes (we only rewrite the first arg).
    $patterns = [
        '#__\(\s*([\'"])([^\'"]+)\1#',
        '#trans\(\s*([\'"])([^\'"]+)\1#',
        '#Lang::get\(\s*([\'"])([^\'"]+)\1#',
        '#@lang\(\s*([\'"])([^\'"]+)\1#', // Blade @lang directive
    ];

    foreach ($patterns as $regex) {
        $content = preg_replace_callback($regex, function ($m) use ($mapping, $rel, &$multiUnresolved) {
            $callMatch = $m[0];
            $quote = $m[1];
            $oldKey = $m[2];

            if (!isset($mapping[$oldKey])) {
                return $callMatch; // not in mapping (e.g. dynamic __('foo.'.$x) won't match anyway)
            }
            $info = $mapping[$oldKey];

            $newKey = null;
            if (($info['file'] ?? '') === '__multi__') {
                foreach ($info['byFile'] as $regex => $target) {
                    if (preg_match($regex, $rel)) {
                        $newKey = $target;
                        break;
                    }
                }
                if ($newKey === null) {
                    $multiUnresolved[] = "$rel: $oldKey";
                    return $callMatch;
                }
            } else {
                $newKey = $info['newKey'];
            }

            // Keep the same call shape (__/trans/etc.) — only swap the literal.
            // Match shape: <head><quote><oldKey><quote>. We rebuild as
            // <head><quote><newKey><quote> so the closing quote is preserved.
            $head = substr($callMatch, 0, strpos($callMatch, $quote));
            return $head . $quote . $newKey . $quote;
        }, $content);
    }

    if ($content !== $orig) {
        $rewriteCount += substr_count($orig, '__(') + substr_count($orig, 'trans(') + substr_count($orig, 'Lang::get(') + substr_count($orig, '@lang(');
        $fileCount++;
        if (!$dryRun) {
            file_put_contents($file, $content);
        }
        // Show a 1-line summary per touched file
        $diffLines = abs(substr_count($orig, "\n") - substr_count($content, "\n"));
        echo "  ✏ $rel\n";
    }
}

echo "\n";
echo "Files touched : $fileCount\n";
echo "Mode          : " . ($dryRun ? 'DRY RUN' : 'APPLIED') . "\n";

if ($multiUnresolved) {
    echo "\nUnresolved multi-target keys (manual review needed):\n";
    foreach ($multiUnresolved as $u) echo "  - $u\n";
    exit(2);
}
