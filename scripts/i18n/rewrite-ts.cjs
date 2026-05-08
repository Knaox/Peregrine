#!/usr/bin/env node
/* eslint-disable */
/**
 * scripts/i18n/rewrite-ts.cjs
 *
 * Mechanically rewrites every t('LITERAL') call in resources/js/**.ts(x) to
 * the new `ns:key` form, driven by scripts/i18n/mapping.json. Also injects
 * `useNamespace(['ns', ...])` at the top of every component that ends up
 * consuming a lazy-loaded namespace, so the JSON chunk is fetched on mount.
 *
 * Approach is regex-driven (no AST) — the codebase has very uniform t() call
 * shapes, and the regexes target literal first arguments only. Anything
 * exotic (computed keys, spread args) is left alone and will be reported as
 * a leftover by verify.sh.
 *
 * Usage:
 *     node scripts/i18n/rewrite-ts.cjs           # apply
 *     node scripts/i18n/rewrite-ts.cjs --dry-run # report only
 */

'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const dryRun = process.argv.includes('--dry-run');

const mapping = JSON.parse(fs.readFileSync(path.join(ROOT, 'scripts/i18n/mapping.json'), 'utf8'));
const frontend = mapping.frontend; // { 'common.next': { ns: 'common', key: 'next' }, ... }

// Eager-loaded namespaces don't need useNamespace() injection.
const EAGER_NAMESPACES = new Set(['common', 'auth-login']);

// Namespaces that exist as JSON files in resources/js/i18n/locales/{en,fr}/
const ALL_NAMESPACES = new Set([
    'common', 'setup', 'auth-login', 'auth-register', 'auth-2fa', 'auth-social',
    'server-overview', 'server-console', 'server-files', 'server-sftp',
    'server-databases', 'server-backups', 'server-schedules', 'server-network',
    'server-shell', 'profile', 'settings-security', 'admin-servers-spa', 'theme-studio',
]);

// ---------------------------------------------------------------------------
// File walk (recursive)
// ---------------------------------------------------------------------------

function walk(dir, results = []) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            if (entry.name === 'node_modules' || entry.name === 'i18n' || entry.name === 'plugins') continue;
            walk(full, results);
        } else if (entry.isFile() && /\.(ts|tsx)$/.test(entry.name)) {
            results.push(full);
        }
    }
    return results;
}

const files = walk(path.join(ROOT, 'resources/js'));
console.log(`Scanning ${files.length} TypeScript files…`);

// ---------------------------------------------------------------------------
// Lookup helpers
// ---------------------------------------------------------------------------

/**
 * Given an old dotted key like "common.next" or "servers.console.start", look
 * up the new (ns, key) pair from mapping.json. Falls back to a prefix match
 * if the exact key isn't there (template literal partials).
 */
function lookup(oldKey) {
    if (frontend[oldKey]) return frontend[oldKey];
    return null;
}

/**
 * For a template literal where the first segment is a literal prefix, find a
 * mapping entry that shares the longest common prefix. Returns
 * { ns, keyPrefix } or null.
 *
 * Example: `servers.status.${state}` → prefix='servers.status.', lookup
 * returns {ns:'server-overview', key:'status.'}
 */
function lookupPrefix(prefixWithDot) {
    // Strip trailing dot for the search; we'll re-add it at the end.
    const base = prefixWithDot.replace(/\.$/, '');
    const candidates = Object.keys(frontend).filter((k) => k === base || k.startsWith(base + '.'));
    if (candidates.length === 0) return null;
    // All candidates must agree on the namespace.
    const namespaces = new Set(candidates.map((k) => frontend[k].ns));
    if (namespaces.size > 1) {
        // Mixed-namespace template — skip, report manually.
        return null;
    }
    const ns = [...namespaces][0];
    // Strip the old prefix from each candidate's `key` and find the common
    // remaining prefix. Easier: just emit `${ns}:${newPrefixWithDot}` where
    // newPrefixWithDot is computed by removing the old prefix from one
    // candidate and re-adding the dot.
    // The new prefix = candidate.key minus the suffix that came from the
    // dynamic part. Since all candidates share the same starting segment
    // after the namespace strip, we compute it from any one.
    const sample = candidates[0];
    const oldTail = sample.substring(base.length); // e.g. ".running"
    const newKeyForSample = frontend[sample].key; // e.g. "status.running"
    const newKeyPrefix = newKeyForSample.substring(0, newKeyForSample.length - oldTail.length);
    return { ns, keyPrefix: newKeyPrefix + (prefixWithDot.endsWith('.') ? '.' : '') };
}

// ---------------------------------------------------------------------------
// Rewrite rules
// ---------------------------------------------------------------------------

// Matches t('foo'), t("foo"), but NOT t(`foo`) or t(variable). Captures the
// literal. Allows whitespace + multiline-safe (no-newline in literal).
const RE_T_STRING = /\bt\(\s*(['"])([A-Za-z][\w.]*?)\1/g;

// ---------------------------------------------------------------------------
// Brutal prefix remap — for keys that exist in code but NOT in en.json
// (i.e. keys consumed via `t('X', { defaultValue: '...' })` where the value
// only ships in the source code itself). Mapping.json doesn't catch them, so
// we apply a deterministic prefix rewrite. Order matters — most specific first.
// ---------------------------------------------------------------------------
const PREFIX_REMAP = [
    // auth.* — most specific subnamespaces first
    [/^auth\.login\./, 'auth-login:'],
    [/^auth\.register\./, 'auth-register:'],
    [/^auth\.2fa\./, 'auth-2fa:'],
    [/^auth\.social\./, 'auth-social:'],
    [/^auth\.providers\./, 'auth-social:providers.'],
    // servers.<subpage>.*
    [/^servers\.list\./, 'server-overview:list.'],
    [/^servers\.bulk\./, 'server-overview:bulk.'],
    [/^servers\.status\./, 'server-overview:status.'],
    [/^servers\.suspended\./, 'server-overview:suspended.'],
    [/^servers\.conflict\./, 'server-overview:conflict.'],
    [/^servers\.install\./, 'server-overview:install.'],
    [/^servers\.operations\./, 'server-overview:operations.'],
    [/^servers\.sync\./, 'server-overview:sync.'],
    [/^servers\.console\./, 'server-console:console.'],
    [/^servers\.power\./, 'server-console:power.'],
    [/^servers\.actions\./, 'server-console:actions.'],
    [/^servers\.files\./, 'server-files:files.'],
    [/^servers\.sftp\./, 'server-sftp:sftp.'],
    [/^servers\.databases\./, 'server-databases:databases.'],
    [/^servers\.backups\./, 'server-backups:backups.'],
    [/^servers\.schedules\./, 'server-schedules:schedules.'],
    [/^servers\.network\./, 'server-network:network.'],
    [/^servers\.sidebar\./, 'server-shell:sidebar.'],
    [/^servers\.detail\./, 'server-shell:detail.'],
    [/^servers\.resources\./, 'server-shell:resources.'],
    [/^servers\.variables\./, 'server-shell:variables.'],
    [/^servers\.settings\./, 'server-shell:settings.'],
    [/^servers\.not_found$/, 'server-shell:not_found'],
    // common chrome
    [/^common\./, 'common:'],
    [/^errors\./, 'common:errors.'],
    [/^nav\./, 'common:nav.'],
    // top-level pages
    [/^setup\./, 'setup:'],
    [/^profile\./, 'profile:'],
    [/^settings\.security\./, 'settings-security:security.'],
    [/^settings\./, 'settings-security:'],
    [/^theme_studio\./, 'theme-studio:'],
    [/^admin\.servers\./, 'admin-servers-spa:servers.'],
    [/^admin\./, 'admin-servers-spa:'],
];

function remapPrefix(oldKey) {
    for (const [re, replacement] of PREFIX_REMAP) {
        if (re.test(oldKey)) {
            return oldKey.replace(re, replacement);
        }
    }
    return null;
}

function nsFromMappedKey(mappedKey) {
    // "common:nav.foo" → "common"
    const idx = mappedKey.indexOf(':');
    return idx >= 0 ? mappedKey.substring(0, idx) : null;
}

// Same for i18n.t('foo')
const RE_I18N_T_STRING = /\bi18n\.t\(\s*(['"])([A-Za-z][\w.]*?)\1/g;

// Template literal with prefix: t(`foo.${...}`) — the LITERAL prefix must
// end on a dot (or contain dots) so we can resolve a namespace.
const RE_T_TEMPLATE = /\bt\(\s*`([A-Za-z][\w.]*\.)\$\{/g;

// useTranslation() calls — pure detection (we don't rewrite these to
// useTranslation('ns') since we now use explicit ns:key form everywhere).
// Keeping them as-is makes the codemod safer.

// ---------------------------------------------------------------------------
// Per-file rewrite
// ---------------------------------------------------------------------------

let touched = 0;
let totalRewrites = 0;
const leftovers = [];

for (const file of files) {
    let src = fs.readFileSync(file, 'utf8');
    const orig = src;
    const rel = path.relative(ROOT, file);
    const usedNamespaces = new Set();

    // Pass 1 — t('literal'). First try the explicit mapping (preserves the
    // refactored leaf key shape); fall back to the brutal prefix remap for
    // keys that don't exist in en.json (e.g. `t('X', { defaultValue: '...' })`).
    src = src.replace(RE_T_STRING, (m, quote, key) => {
        const r = lookup(key);
        if (r) {
            usedNamespaces.add(r.ns);
            return `t(${quote}${r.ns}:${r.key}${quote}`;
        }
        const remapped = remapPrefix(key);
        if (remapped !== null) {
            const ns = nsFromMappedKey(remapped);
            if (ns) usedNamespaces.add(ns);
            return `t(${quote}${remapped}${quote}`;
        }
        // Truly unrelated `t()` (lodash truncate, etc.) — leave alone.
        return m;
    });

    // Pass 2 — i18n.t('literal')
    src = src.replace(RE_I18N_T_STRING, (m, quote, key) => {
        const r = lookup(key);
        if (r) {
            usedNamespaces.add(r.ns);
            return `i18n.t(${quote}${r.ns}:${r.key}${quote}`;
        }
        const remapped = remapPrefix(key);
        if (remapped !== null) {
            const ns = nsFromMappedKey(remapped);
            if (ns) usedNamespaces.add(ns);
            return `i18n.t(${quote}${remapped}${quote}`;
        }
        return m;
    });

    // Pass 3 — t(`prefix.${...}`)
    src = src.replace(RE_T_TEMPLATE, (m, prefix) => {
        const r = lookupPrefix(prefix);
        if (r) {
            usedNamespaces.add(r.ns);
            return `t(\`${r.ns}:${r.keyPrefix}\${`;
        }
        const remapped = remapPrefix(prefix);
        if (remapped !== null) {
            const ns = nsFromMappedKey(remapped);
            if (ns) usedNamespaces.add(ns);
            return `t(\`${remapped}\${`;
        }
        leftovers.push(`${rel}: template literal prefix "${prefix}" not resolvable`);
        return m;
    });

    // ----- Inject useNamespace if the file uses ≥1 lazy namespace -----------
    const lazyUsed = [...usedNamespaces].filter(
        (n) => ALL_NAMESPACES.has(n) && !EAGER_NAMESPACES.has(n),
    ).sort();

    if (lazyUsed.length > 0 && file.endsWith('.tsx')) {
        // Skip injection if the file already has useNamespace
        if (!src.includes('useNamespace(')) {
            // 1. Add import after the LAST CLOSING import statement.
            // We detect single-line `import ... from '...';` AND the closing
            // line of a multi-line `import { … } from '...';` (the line
            // matching `^} from ...;`). Inserting before the closing line of
            // a multi-line import would corrupt the syntax — a bug we hit
            // and explicitly guard against.
            const importStmt = `import { useNamespace } from '@/i18n/useNamespace';`;
            const lines = src.split('\n');
            let lastImportEndLine = -1;
            for (let i = 0; i < lines.length; i++) {
                const line = lines[i];
                // Single-line import
                if (/^\s*import\b.*\bfrom\s+['"][^'"]+['"];?\s*$/.test(line)) {
                    lastImportEndLine = i;
                    continue;
                }
                // Closing line of a multi-line import block: `} from '...';`
                if (/^\s*}\s*from\s+['"][^'"]+['"];?\s*$/.test(line)) {
                    lastImportEndLine = i;
                    continue;
                }
                // Side-effect import: `import 'foo';`
                if (/^\s*import\s+['"][^'"]+['"];?\s*$/.test(line)) {
                    lastImportEndLine = i;
                    continue;
                }
            }
            if (lastImportEndLine >= 0) {
                lines.splice(lastImportEndLine + 1, 0, importStmt);
                src = lines.join('\n');
            }

            // 2. Inject useNamespace([...]) right after the FIRST line that
            // starts a function body, looking for `export function Foo(...)`,
            // `export default function ...`, or `function Foo(...) {`.
            // We use a heuristic: insert right after the first opening `{` on a
            // function declaration line that includes "function " or arrow
            // function with destructured props.
            const namespaceLiteral = JSON.stringify(lazyUsed);
            const callExpr = `    useNamespace(${namespaceLiteral} as const);\n`;

            // Patterns to match a component opening: `function X(...)  {`
            // or `const X = (...) => {`. Insert right after the `{` opening
            // brace ON ITS OWN OR end-of-line.
            const fnPatterns = [
                /(\bexport\s+function\s+\w+\s*\([^)]*\)\s*(?::\s*[^{]+)?\s*\{)/m,
                /(\bexport\s+default\s+function\s+\w+\s*\([^)]*\)\s*(?::\s*[^{]+)?\s*\{)/m,
                /(\bfunction\s+\w+\s*\([^)]*\)\s*(?::\s*[^{]+)?\s*\{)/m,
                /(\bexport\s+const\s+\w+(?:\s*:\s*[^=]+)?\s*=\s*\([^)]*\)\s*(?::\s*[^=>]+)?\s*=>\s*\{)/m,
                /(\bconst\s+\w+(?:\s*:\s*[^=]+)?\s*=\s*\([^)]*\)\s*(?::\s*[^=>]+)?\s*=>\s*\{)/m,
            ];
            let injected = false;
            for (const pat of fnPatterns) {
                const m = src.match(pat);
                if (m && m.index !== undefined) {
                    const insertAt = m.index + m[1].length;
                    src = src.slice(0, insertAt) + '\n' + callExpr.replace(/\n$/, '') + src.slice(insertAt);
                    injected = true;
                    break;
                }
            }
            if (!injected) {
                leftovers.push(`${rel}: useNamespace([${lazyUsed.join(', ')}]) not auto-injected — add manually`);
            }
        }
    }

    if (src !== orig) {
        touched++;
        const rewrites = (src.match(/:[a-z][a-z0-9_]*[`"']/g) || []).length;
        totalRewrites += rewrites;
        console.log(`  ✏ ${rel}  (ns: ${[...usedNamespaces].join(', ')})`);
        if (!dryRun) fs.writeFileSync(file, src);
    }
}

console.log(`\nFiles touched   : ${touched}`);
console.log(`Mode            : ${dryRun ? 'DRY RUN' : 'APPLIED'}`);

if (leftovers.length > 0) {
    console.log(`\nManual review needed (${leftovers.length} entries):`);
    leftovers.forEach((l) => console.log(`  - ${l}`));
}
