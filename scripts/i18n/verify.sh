#!/usr/bin/env bash
# scripts/i18n/verify.sh
# Final regression-proof grep + key-resolution test for the i18n refactor.
# Exit code 0 = clean; non-zero = issues to investigate.

set -uo pipefail

cd "$(dirname "$0")/../.."

echo "=== 1. No legacy backend keys (admin.X, auth.{login,…}.Y, servers.Z) ==="
LEGACY_BACKEND=$(grep -rEn "(__|trans|@lang)\(['\"](admin\.[a-z_]+\.|auth\.(login|register|2fa|providers|social|logout|failed|throttle)|servers\.)" \
    app/ resources/views/ routes/ database/ config/ 2>/dev/null || true)
if [[ -n "$LEGACY_BACKEND" ]]; then
    echo "FOUND legacy backend references:"
    echo "$LEGACY_BACKEND"
    EXIT=1
else
    echo "✓ clean"
fi

echo ""
echo "=== 2. No legacy frontend keys (t('common.X'), t('servers.X.Y'), …) ==="
LEGACY_FRONTEND=$(grep -rEn "\bt\(['\"\`](common|errors|setup|auth|servers|nav|profile|settings|admin|theme_studio)\." \
    resources/js/ 2>/dev/null || true)
if [[ -n "$LEGACY_FRONTEND" ]]; then
    echo "FOUND legacy frontend references:"
    echo "$LEGACY_FRONTEND"
    EXIT=1
else
    echo "✓ clean"
fi

echo ""
echo "=== 3. Every backend target key resolves via Laravel ==="
php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$keys = file("scripts/i18n/all-target-keys.txt", FILE_IGNORE_NEW_LINES);
$missing = [];
foreach ($keys as $k) {
    if (__($k) === $k) $missing[] = $k;
}
if ($missing) {
    echo "MISSING " . count($missing) . " keys:\n";
    foreach (array_slice($missing, 0, 20) as $k) echo "  - $k\n";
    exit(1);
}
echo "✓ ALL " . count($keys) . " target keys resolve\n";
'

echo ""
echo "=== 4. New locale file inventory ==="
echo "Backend (lang/en/):"
find lang/en -type f -name '*.php' | sort | sed 's/^/  /'
echo "Frontend (resources/js/i18n/locales/en/):"
find resources/js/i18n/locales/en -type f -name '*.json' | sort | sed 's/^/  /'

echo ""
echo "=== 5. Legacy files retired ==="
find lang/ -name '*.php.legacy' | sort | sed 's/^/  /'
find resources/js/i18n -name '*.json.legacy' | sort | sed 's/^/  /'

echo ""
echo "=== Done. Exit code: ${EXIT:-0} ==="
exit ${EXIT:-0}
