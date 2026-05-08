#!/usr/bin/env node
/**
 * scripts/i18n/find-missing-keys.cjs
 *
 * Scans every t('ns:key') call in resources/js/**.ts(x) and reports keys
 * that don't exist in the corresponding namespace JSON. These are the keys
 * that render as raw `ns:key` strings in the browser.
 */

'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');

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

function loadNamespace(ns) {
    const p = path.join(ROOT, 'resources/js/i18n/locales/en', `${ns}.json`);
    if (!fs.existsSync(p)) return null;
    return JSON.parse(fs.readFileSync(p, 'utf8'));
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

const files = walk(path.join(ROOT, 'resources/js'));

// Match t('ns:key'), t("ns:key"), and t(`ns:key.${...}`) where prefix is literal up to ${
const RE_T_NS = /\bt\(\s*['"`]([a-z][a-z0-9-]+):([a-zA-Z0-9._-]+?)['"`]/g;
const RE_T_TPL_NS = /\bt\(\s*`([a-z][a-z0-9-]+):([a-zA-Z0-9._-]*)\$\{/g;

const namespaceCache = {};
function ns(name) {
    if (!(name in namespaceCache)) namespaceCache[name] = loadNamespace(name);
    return namespaceCache[name];
}

const missing = []; // { file, line, ns, key }
const tplPrefixUnverified = []; // template literals — we can only check the prefix path

for (const file of files) {
    const src = fs.readFileSync(file, 'utf8');
    const rel = path.relative(ROOT, file);
    const lines = src.split('\n');

    // Calculate line for each match by counting newlines up to match.index
    for (const re of [RE_T_NS]) {
        re.lastIndex = 0;
        let m;
        while ((m = re.exec(src)) !== null) {
            const lineNum = src.substring(0, m.index).split('\n').length;
            const namespace = m[1];
            const key = m[2];
            const tree = ns(namespace);
            if (tree === null) {
                missing.push({ file: rel, line: lineNum, ns: namespace, key, reason: 'namespace JSON does not exist' });
                continue;
            }
            const value = getNested(tree, key);
            if (value === undefined) {
                missing.push({ file: rel, line: lineNum, ns: namespace, key, reason: 'key not in namespace JSON' });
            }
        }
    }

    // Template literals — verify the prefix at least exists as a sub-tree
    RE_T_TPL_NS.lastIndex = 0;
    let m;
    while ((m = RE_T_TPL_NS.exec(src)) !== null) {
        const lineNum = src.substring(0, m.index).split('\n').length;
        const namespace = m[1];
        const prefix = m[2].replace(/\.$/, ''); // strip trailing dot if any
        const tree = ns(namespace);
        if (tree === null) {
            missing.push({ file: rel, line: lineNum, ns: namespace, key: prefix + '*', reason: 'namespace JSON does not exist (template)' });
            continue;
        }
        if (prefix === '') {
            // t(`ns:${dynamic}`) — can't verify, skip
            continue;
        }
        const sub = getNested(tree, prefix);
        if (sub === undefined) {
            tplPrefixUnverified.push({ file: rel, line: lineNum, ns: namespace, prefix, reason: 'template prefix path missing in namespace' });
        }
    }
}

console.log(`Found ${missing.length} missing keys + ${tplPrefixUnverified.length} unresolvable template prefixes.\n`);

if (missing.length) {
    console.log('=== MISSING KEYS (rendered as raw `ns:key` in the browser) ===');
    // Group by namespace for readability
    const byNs = {};
    for (const m of missing) {
        (byNs[m.ns] ||= []).push(m);
    }
    for (const [n, items] of Object.entries(byNs).sort()) {
        console.log(`\n[${n}] (${items.length})`);
        for (const it of items) {
            console.log(`  ${it.file}:${it.line}  →  ${it.ns}:${it.key}  (${it.reason})`);
        }
    }
}

if (tplPrefixUnverified.length) {
    console.log('\n=== TEMPLATE LITERAL PREFIXES not resolvable ===');
    for (const it of tplPrefixUnverified) {
        console.log(`  ${it.file}:${it.line}  →  ${it.ns}:${it.prefix}.\${...}  (${it.reason})`);
    }
}
