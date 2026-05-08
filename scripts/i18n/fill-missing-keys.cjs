#!/usr/bin/env node
/**
 * scripts/i18n/fill-missing-keys.cjs
 *
 * For every t('ns:key') call whose key is absent from the corresponding
 * namespace JSON, extract the literal `defaultValue` from the call site (or
 * the second-argument string in `t('key', 'fallback')`-form) and inject it
 * into BOTH the en/<ns>.json AND fr/<ns>.json. The same string lands in both
 * locales — Damien does the FR translation pass afterwards. This preserves
 * the user-visible behavior byte-for-byte (the defaultValue was what they
 * already saw) while making every code-referenced key resolvable from JSON.
 *
 * Usage:
 *     node scripts/i18n/fill-missing-keys.cjs           # apply
 *     node scripts/i18n/fill-missing-keys.cjs --dry-run
 */

'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const dryRun = process.argv.includes('--dry-run');

function walk(dir, results = []) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            if (entry.name === 'node_modules' || entry.name === 'plugins' || entry.name === 'i18n') continue;
            walk(full, results);
        } else if (entry.isFile() && /\.(ts|tsx)$/.test(entry.name)) {
            results.push(full);
        }
    }
    return results;
}

function loadJson(p) {
    if (!fs.existsSync(p)) return {};
    return JSON.parse(fs.readFileSync(p, 'utf8'));
}

function setNested(tree, dottedKey, value) {
    const parts = dottedKey.split('.');
    let cursor = tree;
    for (let i = 0; i < parts.length - 1; i++) {
        const p = parts[i];
        if (typeof cursor[p] !== 'object' || cursor[p] === null) cursor[p] = {};
        cursor = cursor[p];
    }
    cursor[parts[parts.length - 1]] = value;
}

function getNested(tree, dottedKey) {
    const parts = dottedKey.split('.');
    let cursor = tree;
    for (const p of parts) {
        if (cursor === null || typeof cursor !== 'object' || !(p in cursor)) return undefined;
        cursor = cursor[p];
    }
    return cursor;
}

// ---------------------------------------------------------------------------
// Match shapes:
//   t('ns:key', { defaultValue: 'Some text' })
//   t('ns:key', 'Some text')               // i18next short-form
//   t("ns:key", { defaultValue: "Text" })
// Pulls (ns, key, defaultValue).
// Carefully matches strings that may contain escaped quotes.
// ---------------------------------------------------------------------------

// Strings can be single-quoted, double-quoted, OR backtick (template literal).
// We support all three. The string is captured non-greedily until a matching
// closing quote that's not preceded by a backslash.
const STRING = `(?:'((?:[^'\\\\]|\\\\.)*)'|"((?:[^"\\\\]|\\\\.)*)"|\`((?:[^\`\\\\]|\\\\.)*)\`)`;

// Form A: t('ns:key', { defaultValue: 'X' })
const RE_A = new RegExp(
    `\\bt\\(\\s*${STRING}\\s*,\\s*\\{\\s*[^}]*?defaultValue\\s*:\\s*${STRING}`,
    'gs',
);

// Form B: t('ns:key', 'X')   (i18next short-form — second arg is a string literal directly)
const RE_B = new RegExp(
    `\\bt\\(\\s*${STRING}\\s*,\\s*${STRING}\\s*[),]`,
    'gs',
);

const files = walk(path.join(ROOT, 'resources/js'));

// Map ns → { key → defaultValue }
const collected = {};

for (const file of files) {
    const src = fs.readFileSync(file, 'utf8');

    // Collect all matches from both forms.
    const matches = [];
    let m;

    RE_A.lastIndex = 0;
    while ((m = RE_A.exec(src)) !== null) {
        const fullKey = (m[1] ?? m[2] ?? m[3] ?? '').replace(/\\(.)/g, '$1');
        const defVal = (m[4] ?? m[5] ?? m[6] ?? '').replace(/\\(.)/g, '$1');
        matches.push({ fullKey, defVal });
    }

    RE_B.lastIndex = 0;
    while ((m = RE_B.exec(src)) !== null) {
        const fullKey = (m[1] ?? m[2] ?? m[3] ?? '').replace(/\\(.)/g, '$1');
        const defVal = (m[4] ?? m[5] ?? m[6] ?? '').replace(/\\(.)/g, '$1');
        // Filter out form-A overlaps (form A already matched, the substring would also match form B — skip)
        if (defVal.includes('{') || defVal.length === 0) continue;
        matches.push({ fullKey, defVal });
    }

    for (const { fullKey, defVal } of matches) {
        const colonIdx = fullKey.indexOf(':');
        if (colonIdx < 0) continue; // not a ns:key form
        const ns = fullKey.substring(0, colonIdx);
        const key = fullKey.substring(colonIdx + 1);
        if (!ns || !key) continue;
        // Skip dynamic keys with template ${} placeholders that survived
        if (key.includes('${') || ns.includes('${')) continue;
        collected[ns] ??= {};
        // First write wins — multiple call sites of the same key will keep
        // the first defaultValue we see. They're usually identical anyway.
        if (!(key in collected[ns])) {
            collected[ns][key] = defVal;
        }
    }
}

// Now compare to existing JSON and inject only what's missing.
let injected = 0;
for (const [ns, kvs] of Object.entries(collected)) {
    for (const locale of ['en', 'fr']) {
        const p = path.join(ROOT, `resources/js/i18n/locales/${locale}/${ns}.json`);
        const tree = loadJson(p);
        let changed = false;
        for (const [key, defVal] of Object.entries(kvs)) {
            if (getNested(tree, key) === undefined) {
                setNested(tree, key, defVal);
                changed = true;
                if (locale === 'en') {
                    injected++;
                    console.log(`  + ${ns}:${key}  =  ${JSON.stringify(defVal).substring(0, 80)}`);
                }
            }
        }
        if (changed && !dryRun) {
            fs.writeFileSync(
                p,
                JSON.stringify(tree, null, 4) + '\n',
                'utf8',
            );
        }
    }
}

console.log(`\nInjected ${injected} missing keys into both en/ and fr/ JSON namespaces.`);
console.log(`Mode: ${dryRun ? 'DRY RUN' : 'APPLIED'}`);
console.log(`\nNext: run a translation pass on the new fr/* values (currently mirrored from EN).`);
