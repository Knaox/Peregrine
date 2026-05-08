<?php
/**
 * scripts/i18n/build-mapping.php
 *
 * Generates `scripts/i18n/mapping.json` — the single source of truth for the
 * i18n refactor. For every leaf key in the existing `lang/en/*.php` files and
 * `resources/js/i18n/en.json`, computes a (new file, new key) target.
 *
 * Run from repository root:
 *     php scripts/i18n/build-mapping.php
 *
 * Output:
 *     - scripts/i18n/mapping.json          (machine-readable mapping)
 *     - scripts/i18n/all-target-keys.txt   (one new key per line, for the
 *                                            verification test)
 *
 * Aborts with a non-zero exit code on any orphan key, target collision, or
 * one-to-many rule with an unresolved per-file disambiguator.
 */

declare(strict_types=1);

const ROOT = __DIR__ . '/../..';

/* ============================================================
 * Section rules — backend
 * ============================================================
 *
 * Each rule maps an "old key prefix" to a "new file + new prefix".
 * Order matters: longest prefix wins.
 */

/**
 * Returns ['file' => 'admin/_shell', 'prefix' => 'admin/_shell.navigation', 'remainder' => 'groups.servers']
 * for an old key like 'admin.navigation.groups.servers'.
 *
 * Returns null when the key has no rule (will be reported as orphan).
 */
function backendRule(string $oldKey): ?array
{
    // ---------- admin.* -----------------------------------------
    static $shellSections = [
        'navigation', 'common', 'badges', 'tabs', 'statuses',
        'http_filters', 'fields', 'actions', 'notifications',
        'partials', 'profile', 'sync_order',
    ];

    foreach ($shellSections as $section) {
        if (str_starts_with($oldKey, "admin.$section.") || $oldKey === "admin.$section") {
            $tail = str_starts_with($oldKey, "admin.$section.")
                ? substr($oldKey, strlen("admin.$section."))
                : '';
            return [
                'file' => 'admin/_shell',
                'newKey' => trim("admin/_shell.$section" . ($tail ? ".$tail" : ''), '.'),
            ];
        }
    }

    // admin.resources.<resource>.* — Filament resource label/plural/navigation
    // Live resources go into their own resource file under .resource.* subkey.
    // Virtual / unused resources (pelican_*) are parked under _shell.resources.*
    static $liveResources = [
        'users' => 'admin/users',
        'servers' => 'admin/servers',
        'server_plans' => 'admin/server_plans',
        'eggs' => 'admin/eggs',
        'nodes' => 'admin/nodes',
    ];
    static $logResources = [
        'pelican_webhook_logs' => 'pelican_webhook',
        'bridge_sync_logs' => 'bridge',
        'sync_logs' => 'sync',
    ];
    static $virtualResources = [
        'pelican_backups', 'pelican_allocations', 'pelican_server_transfers',
    ];

    if (preg_match('#^admin\.resources\.([^.]+)(?:\.(.*))?$#', $oldKey, $m)) {
        $name = $m[1];
        $tail = $m[2] ?? '';
        if (isset($liveResources[$name])) {
            $file = $liveResources[$name];
            return [
                'file' => $file,
                'newKey' => $file . '.resource' . ($tail !== '' ? ".$tail" : ''),
            ];
        }
        if (isset($logResources[$name])) {
            $sub = $logResources[$name];
            return [
                'file' => 'admin/logs',
                'newKey' => "admin/logs.$sub.resource" . ($tail !== '' ? ".$tail" : ''),
            ];
        }
        if (in_array($name, $virtualResources, true)) {
            return [
                'file' => 'admin/_shell',
                'newKey' => "admin/_shell.resources.$name" . ($tail !== '' ? ".$tail" : ''),
            ];
        }
        return null; // unknown resource
    }

    // admin.<resource>.* — direct resource customizations
    // (admin.servers.*, admin.users.*, admin.plans.*, admin.eggs.*)
    static $resourceDirect = [
        'servers' => 'admin/servers',
        'users' => 'admin/users',
        'plans' => 'admin/server_plans',
        'eggs' => 'admin/eggs',
    ];
    foreach ($resourceDirect as $name => $file) {
        if (str_starts_with($oldKey, "admin.$name.") || $oldKey === "admin.$name") {
            $tail = str_starts_with($oldKey, "admin.$name.")
                ? substr($oldKey, strlen("admin.$name."))
                : '';
            return [
                'file' => $file,
                'newKey' => $file . ($tail !== '' ? ".$tail" : ''),
            ];
        }
    }

    // admin.logs.* — shared logs columns (consumed by 3 log resources)
    if (str_starts_with($oldKey, 'admin.logs.') || $oldKey === 'admin.logs') {
        $tail = str_starts_with($oldKey, 'admin.logs.')
            ? substr($oldKey, strlen('admin.logs.'))
            : '';
        return [
            'file' => 'admin/logs',
            'newKey' => 'admin/logs' . ($tail !== '' ? ".$tail" : ''),
        ];
    }

    // admin.widgets.*
    if (str_starts_with($oldKey, 'admin.widgets.') || $oldKey === 'admin.widgets') {
        $tail = str_starts_with($oldKey, 'admin.widgets.')
            ? substr($oldKey, strlen('admin.widgets.'))
            : '';
        return [
            'file' => 'admin/widgets',
            'newKey' => 'admin/widgets' . ($tail !== '' ? ".$tail" : ''),
        ];
    }

    // admin.pages.<page>.* — Filament Page meta (title/navigation/subtitle)
    // Goes into the per-page file under .page.* subkey.
    static $filamentPages = [
        'settings' => 'admin/settings',
        'auth_settings' => 'admin/auth_settings',
        'theme_settings' => 'admin/theme_settings',
        'bridge_settings' => 'admin/bridge_settings',
        'email_templates' => 'admin/email_templates',
        'plugins' => 'admin/plugins',
        'pelican_webhook_settings' => 'admin/pelican_webhook',
        'about' => 'admin/about',
    ];
    if (preg_match('#^admin\.pages\.([^.]+)(?:\.(.*))?$#', $oldKey, $m)) {
        $page = $m[1];
        $tail = $m[2] ?? '';
        if (isset($filamentPages[$page])) {
            $file = $filamentPages[$page];
            return [
                'file' => $file,
                'newKey' => $file . '.page' . ($tail !== '' ? ".$tail" : ''),
            ];
        }
        return null;
    }

    // admin.<page>_form.* + admin.<page>.* (page-specific business strings)
    static $pageForms = [
        'settings_form' => ['file' => 'admin/settings', 'sub' => 'form'],
        'auth_form' => ['file' => 'admin/auth_settings', 'sub' => 'form'],
        'theme_form' => ['file' => 'admin/theme_settings', 'sub' => 'form'],
        'bridge_form' => ['file' => 'admin/bridge_settings', 'sub' => 'form'],
        'email_templates' => ['file' => 'admin/email_templates', 'sub' => 'form'],
        'mail_registry' => ['file' => 'admin/email_templates', 'sub' => 'registry'],
        'webhook_settings' => ['file' => 'admin/pelican_webhook', 'sub' => null],
        'about' => ['file' => 'admin/about', 'sub' => null],
        'plugins' => ['file' => 'admin/plugins', 'sub' => null],
    ];
    foreach ($pageForms as $section => $info) {
        if (str_starts_with($oldKey, "admin.$section.") || $oldKey === "admin.$section") {
            $tail = str_starts_with($oldKey, "admin.$section.")
                ? substr($oldKey, strlen("admin.$section."))
                : '';
            $file = $info['file'];
            $sub = $info['sub'];
            $newKey = $file . ($sub !== null ? ".$sub" : '');
            if ($tail !== '') $newKey .= ".$tail";
            return ['file' => $file, 'newKey' => $newKey];
        }
    }

    // admin.resource_pages.* — shared page strings dispatched per-context
    if (str_starts_with($oldKey, 'admin.resource_pages.')) {
        $tail = substr($oldKey, strlen('admin.resource_pages.'));
        // Standalone "imported" toasts (notification message after sync completes)
        // Map to per-resource sync.imported leaf (not a sub of sync_users to keep
        // the resource file's tree shallow).
        if ($tail === 'sync_users_imported') {
            return ['file' => 'admin/users', 'newKey' => 'admin/users.sync.imported'];
        }
        if ($tail === 'sync_servers_imported') {
            return ['file' => 'admin/servers', 'newKey' => 'admin/servers.sync.imported'];
        }
        // Sync action labels: admin.resource_pages.sync_users → admin/users.sync (etc.)
        $resourceSyncMap = [
            'sync_users' => 'admin/users',
            'sync_servers' => 'admin/servers',
            'sync_eggs' => 'admin/eggs',
            'sync_nodes' => 'admin/nodes',
            'sync_plans' => 'admin/server_plans',
        ];
        foreach ($resourceSyncMap as $prefix => $file) {
            if ($tail === $prefix || str_starts_with($tail, $prefix . '.')) {
                $sub = $tail === $prefix ? '' : substr($tail, strlen($prefix . '.'));
                $newKey = "$file.sync" . ($sub !== '' ? ".$sub" : '');
                return ['file' => $file, 'newKey' => $newKey];
            }
        }
        // test_server.* — under server_plans
        if (str_starts_with($tail, 'test_server.') || $tail === 'test_server') {
            $sub = $tail === 'test_server' ? '' : substr($tail, strlen('test_server.'));
            $newKey = 'admin/server_plans.test_server' . ($sub !== '' ? ".$sub" : '');
            return ['file' => 'admin/server_plans', 'newKey' => $newKey];
        }
        // back_to_list — one-to-many: must be disambiguated by file at codemod time.
        if ($tail === 'back_to_list') {
            return [
                'file' => '__multi__',
                'newKey' => 'admin/<resource>.back_to_list',
                'multiTargets' => [
                    'admin/servers.back_to_list',
                    'admin/users.back_to_list',
                    'admin/eggs.back_to_list',
                    'admin/nodes.back_to_list',
                    'admin/server_plans.back_to_list',
                    'admin/logs.back_to_list',
                ],
                'byFile' => [
                    '#app/Filament/Resources/ServerResource(/|\.)#' => 'admin/servers.back_to_list',
                    '#app/Filament/Resources/UserResource(/|\.)#' => 'admin/users.back_to_list',
                    '#app/Filament/Resources/EggResource(/|\.)#' => 'admin/eggs.back_to_list',
                    '#app/Filament/Resources/NodeResource(/|\.)#' => 'admin/nodes.back_to_list',
                    '#app/Filament/Resources/ServerPlanResource(/|\.)#' => 'admin/server_plans.back_to_list',
                    '#app/Filament/Resources/(BridgeSyncLogResource|SyncLogResource|PelicanWebhookLogResource)(/|\.)#' => 'admin/logs.back_to_list',
                ],
            ];
        }
        return null;
    }

    // ---------- auth.* ------------------------------------------
    if (str_starts_with($oldKey, 'auth.login.')) {
        return ['file' => 'auth/login', 'newKey' => 'auth/login.' . substr($oldKey, strlen('auth.login.'))];
    }
    if ($oldKey === 'auth.logout' || $oldKey === 'auth.failed' || $oldKey === 'auth.throttle') {
        $sub = substr($oldKey, strlen('auth.'));
        return ['file' => 'auth/login', 'newKey' => "auth/login.$sub"];
    }
    if (str_starts_with($oldKey, 'auth.register.')) {
        return ['file' => 'auth/register', 'newKey' => 'auth/register.' . substr($oldKey, strlen('auth.register.'))];
    }
    if (str_starts_with($oldKey, 'auth.2fa.')) {
        return ['file' => 'auth/2fa', 'newKey' => 'auth/2fa.' . substr($oldKey, strlen('auth.2fa.'))];
    }
    if (str_starts_with($oldKey, 'auth.providers.')) {
        return ['file' => 'auth/social', 'newKey' => 'auth/social.providers.' . substr($oldKey, strlen('auth.providers.'))];
    }
    if (str_starts_with($oldKey, 'auth.social.')) {
        return ['file' => 'auth/social', 'newKey' => 'auth/social.' . substr($oldKey, strlen('auth.social.'))];
    }

    // ---------- bridge.* / pelican.* / validation.* — unchanged
    if (str_starts_with($oldKey, 'bridge.') || $oldKey === 'bridge') {
        return ['file' => 'bridge', 'newKey' => $oldKey];
    }
    if (str_starts_with($oldKey, 'pelican.') || $oldKey === 'pelican') {
        return ['file' => 'pelican', 'newKey' => $oldKey];
    }
    if (str_starts_with($oldKey, 'validation.') || $oldKey === 'validation') {
        return ['file' => 'validation', 'newKey' => $oldKey];
    }

    // ---------- servers.* — frontend-only, file deleted
    // We still produce a mapping so build-new-locale-files can copy values
    // somewhere if a future audit finds a backend usage. For now, target
    // 'servers' file (no-op rename) and let verify.sh confirm zero usage.
    if (str_starts_with($oldKey, 'servers.') || $oldKey === 'servers') {
        return ['file' => 'servers', 'newKey' => $oldKey, 'deprecated' => true];
    }

    return null;
}

/* ============================================================
 * Section rules — frontend (en.json → namespaces)
 * ============================================================ */

/**
 * Returns ['ns' => 'common', 'key' => 'next'] for an old key like 'common.next'.
 */
function frontendRule(string $oldKey): ?array
{
    // common.* / errors.* / nav.* → common namespace (eager)
    if (str_starts_with($oldKey, 'common.')) {
        return ['ns' => 'common', 'key' => substr($oldKey, strlen('common.'))];
    }
    if (str_starts_with($oldKey, 'errors.')) {
        return ['ns' => 'common', 'key' => 'errors.' . substr($oldKey, strlen('errors.'))];
    }
    if (str_starts_with($oldKey, 'nav.')) {
        return ['ns' => 'common', 'key' => 'nav.' . substr($oldKey, strlen('nav.'))];
    }

    // setup.*
    if (str_starts_with($oldKey, 'setup.')) {
        return ['ns' => 'setup', 'key' => substr($oldKey, strlen('setup.'))];
    }

    // auth.login.* / auth.register.* / auth.2fa.* / auth.social.* / auth.providers.*
    if (str_starts_with($oldKey, 'auth.login.')) {
        return ['ns' => 'auth-login', 'key' => substr($oldKey, strlen('auth.login.'))];
    }
    if (str_starts_with($oldKey, 'auth.register.')) {
        return ['ns' => 'auth-register', 'key' => substr($oldKey, strlen('auth.register.'))];
    }
    if (str_starts_with($oldKey, 'auth.2fa.')) {
        return ['ns' => 'auth-2fa', 'key' => substr($oldKey, strlen('auth.2fa.'))];
    }
    if (str_starts_with($oldKey, 'auth.social.')) {
        return ['ns' => 'auth-social', 'key' => substr($oldKey, strlen('auth.social.'))];
    }
    if (str_starts_with($oldKey, 'auth.providers.')) {
        return ['ns' => 'auth-social', 'key' => 'providers.' . substr($oldKey, strlen('auth.providers.'))];
    }
    if (str_starts_with($oldKey, 'auth.')) {
        // Any remaining auth.* (e.g. auth.invite.*) — bucket into auth-login as catch-all
        return ['ns' => 'auth-login', 'key' => substr($oldKey, strlen('auth.'))];
    }

    // servers.<sub>.* — split per page
    static $serverPageMap = [
        'list' => 'server-overview',
        'bulk' => 'server-overview',
        'status' => 'server-overview',
        'suspended' => 'server-overview',
        'conflict' => 'server-overview',
        'install' => 'server-overview',
        'operations' => 'server-overview',
        'console' => 'server-console',
        'power' => 'server-console',
        'actions' => 'server-console',
        'files' => 'server-files',
        'sftp' => 'server-sftp',
        'databases' => 'server-databases',
        'backups' => 'server-backups',
        'schedules' => 'server-schedules',
        'network' => 'server-network',
        'sidebar' => 'server-shell',
        'detail' => 'server-shell',
        'resources' => 'server-shell',
        'variables' => 'server-shell',
        'settings' => 'server-shell',
        'not_found' => 'server-shell',
        'sync' => 'server-overview', // servers.sync.* (success/error notifs)
    ];
    if (preg_match('#^servers\.([^.]+)(?:\.(.*))?$#', $oldKey, $m)) {
        $section = $m[1];
        $tail = $m[2] ?? '';
        if (isset($serverPageMap[$section])) {
            $ns = $serverPageMap[$section];
            return [
                'ns' => $ns,
                'key' => $section . ($tail !== '' ? ".$tail" : ''),
            ];
        }
        return null;
    }

    // profile.*
    if (str_starts_with($oldKey, 'profile.')) {
        return ['ns' => 'profile', 'key' => substr($oldKey, strlen('profile.'))];
    }

    // settings.security.* → settings-security
    if (str_starts_with($oldKey, 'settings.security.')) {
        return ['ns' => 'settings-security', 'key' => 'security.' . substr($oldKey, strlen('settings.security.'))];
    }
    if (str_starts_with($oldKey, 'settings.')) {
        // Any non-security settings → bucket into settings-security as catch-all
        return ['ns' => 'settings-security', 'key' => substr($oldKey, strlen('settings.'))];
    }

    // admin.servers.* → admin-servers-spa
    if (str_starts_with($oldKey, 'admin.servers.')) {
        return ['ns' => 'admin-servers-spa', 'key' => 'servers.' . substr($oldKey, strlen('admin.servers.'))];
    }
    if (str_starts_with($oldKey, 'admin.')) {
        return ['ns' => 'admin-servers-spa', 'key' => substr($oldKey, strlen('admin.'))];
    }

    // theme_studio.*
    if (str_starts_with($oldKey, 'theme_studio.')) {
        return ['ns' => 'theme-studio', 'key' => substr($oldKey, strlen('theme_studio.'))];
    }

    return null;
}

/* ============================================================
 * Walk helpers
 * ============================================================ */

function flatten(array $arr, string $prefix = ''): array
{
    $out = [];
    foreach ($arr as $k => $v) {
        $key = $prefix === '' ? (string)$k : "$prefix.$k";
        if (is_array($v) && !array_is_list($v) && $v !== []) {
            // Treat assoc arrays as nested namespaces
            $out += flatten($v, $key);
        } elseif (is_array($v) && array_is_list($v)) {
            // Lists (like ['label' => ..., 'plural' => ...]) — already handled above as assoc.
            // True lists are rare in i18n; keep as a leaf.
            $out[$key] = $v;
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

/* ============================================================
 * Main
 * ============================================================ */

$backendFiles = [
    'admin' => ROOT . '/lang/en/admin.php',
    'auth' => ROOT . '/lang/en/auth.php',
    'bridge' => ROOT . '/lang/en/bridge.php',
    'pelican' => ROOT . '/lang/en/pelican.php',
    'servers' => ROOT . '/lang/en/servers.php',
    'validation' => ROOT . '/lang/en/validation.php',
];

$backendMapping = [];
$orphans = [];
$collisions = [];
$targetSet = [];

foreach ($backendFiles as $top => $path) {
    $arr = require $path;
    $flat = flatten($arr, $top);
    foreach ($flat as $oldKey => $value) {
        $rule = backendRule($oldKey);
        if ($rule === null) {
            $orphans[] = $oldKey;
            continue;
        }
        if (($rule['file'] ?? '') === '__multi__') {
            $backendMapping[$oldKey] = [
                'file' => '__multi__',
                'multiTargets' => $rule['multiTargets'],
                'byFile' => $rule['byFile'],
            ];
            // Each multiTarget claims the new key — register all to detect collisions
            foreach ($rule['multiTargets'] as $t) {
                $targetSet[$t] = ($targetSet[$t] ?? 0) + 1;
            }
            continue;
        }
        $backendMapping[$oldKey] = [
            'file' => $rule['file'],
            'newKey' => $rule['newKey'],
        ] + (isset($rule['deprecated']) ? ['deprecated' => true] : []);
        if (isset($targetSet[$rule['newKey']])) {
            $collisions[$rule['newKey']][] = $oldKey;
        }
        $targetSet[$rule['newKey']] = ($targetSet[$rule['newKey']] ?? 0) + 1;
    }
}

// Frontend
$frontendArr = json_decode(file_get_contents(ROOT . '/resources/js/i18n/en.json'), true);
$frontendFlat = flattenJson($frontendArr);
$frontendMapping = [];
$frontendOrphans = [];
$frontendTargets = [];

foreach ($frontendFlat as $oldKey => $value) {
    $rule = frontendRule($oldKey);
    if ($rule === null) {
        $frontendOrphans[] = $oldKey;
        continue;
    }
    $frontendMapping[$oldKey] = $rule;
    $tgt = $rule['ns'] . ':' . $rule['key'];
    $frontendTargets[$tgt] = ($frontendTargets[$tgt] ?? 0) + 1;
}

// Report
echo "=== build-mapping.php ===\n";
echo "Backend old keys     : " . count($backendMapping) . "\n";
echo "Backend orphans      : " . count($orphans) . "\n";
if ($orphans) {
    echo "  ORPHANS:\n";
    foreach ($orphans as $o) echo "    - $o\n";
}
echo "Backend collisions   : " . count($collisions) . "\n";
if ($collisions) {
    echo "  COLLISIONS:\n";
    foreach ($collisions as $newKey => $oldKeys) {
        echo "    $newKey ← " . implode(', ', $oldKeys) . "\n";
    }
}
echo "Frontend old keys    : " . count($frontendMapping) . "\n";
echo "Frontend orphans     : " . count($frontendOrphans) . "\n";
if ($frontendOrphans) {
    echo "  ORPHANS:\n";
    foreach ($frontendOrphans as $o) echo "    - $o\n";
}
$frontendCollisions = array_filter($frontendTargets, fn($n) => $n > 1);
echo "Frontend collisions  : " . count($frontendCollisions) . "\n";
if ($frontendCollisions) {
    foreach ($frontendCollisions as $tgt => $n) echo "    $tgt × $n\n";
}

if ($orphans || $collisions || $frontendOrphans || $frontendCollisions) {
    fwrite(STDERR, "\nABORT: orphans or collisions detected. Fix the rules in this script and re-run.\n");
    exit(1);
}

$out = [
    'backend' => $backendMapping,
    'frontend' => $frontendMapping,
];
file_put_contents(
    ROOT . '/scripts/i18n/mapping.json',
    json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
);

// Emit list of all target backend keys (for the verification test)
$allTargetKeys = [];
foreach ($backendMapping as $oldKey => $info) {
    if (($info['file'] ?? '') === '__multi__') {
        foreach ($info['multiTargets'] as $t) $allTargetKeys[$t] = true;
    } elseif (empty($info['deprecated'])) {
        $allTargetKeys[$info['newKey']] = true;
    }
}
ksort($allTargetKeys);
file_put_contents(
    ROOT . '/scripts/i18n/all-target-keys.txt',
    implode("\n", array_keys($allTargetKeys)) . "\n"
);

echo "\nWrote scripts/i18n/mapping.json (" . count($backendMapping) . " backend + " . count($frontendMapping) . " frontend keys)\n";
echo "Wrote scripts/i18n/all-target-keys.txt (" . count($allTargetKeys) . " unique backend target keys)\n";
