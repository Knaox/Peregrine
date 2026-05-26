// Peregrine game-query sidecar — Player Counter plugin.
//
// A tiny loopback HTTP service that wraps GameDig so the panel can ask
// "how many players are on host:port for game <type>?" over plain JSON. The
// panel caches the answer; the sidecar reaches the game servers (and, for EOS
// games, Epic's API).
//
//   POST /query   { "type": "asa", "host": "1.2.3.4", "port": 7777, "family": "eos" }
//     -> 200      { "ok": true,  "online": 7, "max": 70, "name": "...", "players": ["..."] }
//     -> 200      { "ok": false, "error": "..." }   (server down / bad type)
//   GET  /healthz -> 200 { "ok": true }
//
// `family` routes the query: "eos" -> Epic API (with public-IP rewrite),
// "hytale" -> Nitrado query-mod HTTP endpoint, anything else -> GameDig with
// the given `type` (incl. "protocol-valve" for the generic A2S/Steam probe).
//
// Run:  cd plugins/peregrine-player-counter/sidecar && npm install && node index.mjs
// Env:  GAME_QUERY_PORT (9899), GAME_QUERY_HOST (127.0.0.1), GAME_QUERY_TOKEN,
//       GAME_QUERY_TIMEOUT_MS (5000), GAME_QUERY_EOS_TIMEOUT_MS (6000),
//       GAME_QUERY_MAX_NAMES (5), GAME_QUERY_MAX_BODY (4096)

import { createServer } from 'node:http';
import dns from 'node:dns';
import { GameDig } from 'gamedig';
import WebSocket from 'ws';

const PORT = Number(process.env.GAME_QUERY_PORT ?? 9899);
const HOST = process.env.GAME_QUERY_HOST ?? '127.0.0.1';
const TOKEN = process.env.GAME_QUERY_TOKEN ?? '';
const TIMEOUT_MS = Number(process.env.GAME_QUERY_TIMEOUT_MS ?? 5000);
const EOS_TIMEOUT_MS = Number(process.env.GAME_QUERY_EOS_TIMEOUT_MS ?? 6000);
const MAX_NAMES = Number(process.env.GAME_QUERY_MAX_NAMES ?? 5);
const MAX_BODY = Number(process.env.GAME_QUERY_MAX_BODY ?? 8192);
// Console-count (crossplay games like Valheim that expose no A2S): how long to
// keep the Wings websocket open collecting the log backlog before parsing.
const CONSOLE_SETTLE_MS = Number(process.env.GAME_QUERY_CONSOLE_SETTLE_MS ?? 700);
const CONSOLE_MAX_MS = Number(process.env.GAME_QUERY_CONSOLE_MAX_MS ?? 4000);

// EOS matches sessions by the server's PUBLIC IP. The host we receive may be an
// internal alias (split-horizon DNS resolves it to a LAN IP), so for EOS we
// resolve it through a public resolver to get the real, Cloudflare-published IP.
const PUBLIC_DNS = (process.env.GAME_QUERY_DNS ?? '1.1.1.1,8.8.8.8').split(',').map((s) => s.trim()).filter(Boolean);
const publicResolver = new dns.promises.Resolver();
try { publicResolver.setServers(PUBLIC_DNS); } catch { /* fall back to system resolver */ }

function isIp(host) {
    return /^\d{1,3}(\.\d{1,3}){3}$/.test(host) || host.includes(':');
}

async function toPublicIp(host) {
    if (isIp(host)) return host;
    try {
        const ips = await publicResolver.resolve4(host);
        return ips[0] ?? host;
    } catch {
        return host;
    }
}

function send(res, status, body) {
    const json = JSON.stringify(body);
    res.writeHead(status, {
        'content-type': 'application/json',
        'content-length': Buffer.byteLength(json),
    });
    res.end(json);
}

function readBody(req) {
    return new Promise((resolve, reject) => {
        let raw = '';
        req.on('data', (chunk) => {
            raw += chunk;
            if (raw.length > MAX_BODY) {
                reject(new Error('payload too large'));
                req.destroy();
            }
        });
        req.on('end', () => resolve(raw));
        req.on('error', reject);
    });
}

function authorized(req) {
    if (!TOKEN) return true;
    return (req.headers['authorization'] ?? '') === `Bearer ${TOKEN}`;
}

async function runQuery(type, host, port, timeoutMs) {
    const state = await GameDig.query({
        type,
        host,
        port,
        // Bound the per-packet wait so the UDP sub-protocols GameDig tries for
        // 'minecraft' (gamespy3/bedrock) fail fast instead of dragging the whole
        // query past the PHP-side timeout when only the TCP SLP answers.
        socketTimeout: Math.min(timeoutMs, 2500),
        attemptTimeout: timeoutMs,
        maxRetries: 0,
    });

    const online = Number.isFinite(state.numplayers)
        ? state.numplayers
        : Array.isArray(state.players) ? state.players.length : 0;
    const max = Number.isFinite(state.maxplayers) ? state.maxplayers : null;

    // Up to MAX_NAMES non-empty player names, when the game exposes them. Many
    // only return a partial sample (Minecraft) or none; for EOS/ARK these are
    // Epic account ids, not in-game survivor names.
    const players = Array.isArray(state.players)
        ? state.players
            .map((p) => (typeof p?.name === 'string' ? p.name.trim() : ''))
            .filter((n) => n.length > 0)
            .slice(0, MAX_NAMES)
        : [];

    return { online: Math.max(0, online), max, name: state.name ?? null, players };
}

// Hytale isn't a GameDig type in our pinned gamedig (5.3.2), so we speak its
// query protocol directly: the nitrado/hytale-plugin-query mod exposes a plain
// HTTP+JSON endpoint at /Nitrado/Query. Same shape GameDig's own hytale handler
// reads, and unlike A2S/EOS it returns real in-game player names.
async function queryHytale(host, port, timeoutMs) {
    const res = await fetch(`http://${host}:${port}/Nitrado/Query`, {
        headers: { accept: 'application/json' },
        signal: AbortSignal.timeout(timeoutMs),
    });

    if (!res.ok) throw new Error(`hytale query HTTP ${res.status}`);

    const data = await res.json();
    const server = data?.Server ?? {};
    const universe = data?.Universe ?? {};

    const online = Number.isFinite(universe.CurrentPlayers) ? universe.CurrentPlayers : 0;
    const max = Number.isFinite(server.MaxPlayers) ? server.MaxPlayers : null;
    const players = Array.isArray(data?.Players)
        ? data.Players
            .map((p) => (typeof p?.Name === 'string' ? p.Name.trim() : ''))
            .filter((n) => n.length > 0)
            .slice(0, MAX_NAMES)
        : [];

    return { online: Math.max(0, online), max, name: server.Name ?? null, players };
}

async function handleQuery(req, res) {
    if (!authorized(req)) return send(res, 401, { ok: false, error: 'unauthorized' });

    let payload;
    try {
        payload = JSON.parse((await readBody(req)) || '{}');
    } catch {
        return send(res, 400, { ok: false, error: 'invalid json' });
    }

    const { type, host } = payload;
    const port = Number(payload.port);
    const family = typeof payload.family === 'string' ? payload.family : '';
    if (!type || !host || !Number.isInteger(port) || port < 1 || port > 65535) {
        return send(res, 400, { ok: false, error: 'type, host and port are required' });
    }

    // EOS talks to the Epic API (a couple of HTTPS round-trips) — give it more time.
    const isEos = family === 'eos';
    const isHytale = family === 'hytale' || type === 'hytale';
    const timeoutMs = isEos ? Math.max(TIMEOUT_MS, EOS_TIMEOUT_MS) : TIMEOUT_MS;

    try {
        // Hytale: query the Nitrado mod's HTTP endpoint directly (not a GameDig
        // type here). It's a local request like A2S, so no public-IP rewrite.
        if (isHytale) {
            const result = await queryHytale(host, port, timeoutMs);
            return send(res, 200, { ok: true, online: result.online, max: result.max, name: result.name, players: result.players });
        }

        // EOS is matched by the server's PUBLIC IP — resolve internal aliases to
        // the public (Cloudflare-published) IP first; other families query as-is.
        const queryHost = isEos ? await toPublicIp(host) : host;
        let result = await runQuery(type, queryHost, port, timeoutMs);

        // node-gamedig #654: the Epic API can return a duplicate session whose
        // totalPlayers is always 1, and gamedig's .find() may pick that bogus
        // one (it doesn't expose the other sessions, so we can't filter them).
        // A real session reports the true count, so an EOS result of exactly 1
        // is suspicious — re-query once and keep the higher count.
        if (isEos && result.online === 1) {
            try {
                const retry = await runQuery(type, queryHost, port, timeoutMs);
                if (retry.online > result.online) result = retry;
            } catch {
                /* keep the first result */
            }
        }

        return send(res, 200, { ok: true, online: result.online, max: result.max, name: result.name, players: result.players });
    } catch (err) {
        // A failed query almost always means the server is offline/unreachable.
        return send(res, 200, { ok: false, error: String(err?.message ?? err) });
    }
}

/**
 * Open the Wings server websocket, request the console log backlog, collect the
 * `console output` lines until the burst goes quiet, then close. Short-lived and
 * stateless — no token refresh needed. Wings validates the Origin header against
 * the panel URL, so the caller must pass it.
 */
function readConsole(socket, token, origin) {
    return new Promise((resolve, reject) => {
        const lines = [];
        let settle, hard, done = false;
        const ws = new WebSocket(socket, {
            headers: origin ? { Origin: origin } : {},
            handshakeTimeout: 5000,
        });
        const finish = (err) => {
            if (done) return;
            done = true;
            clearTimeout(settle);
            clearTimeout(hard);
            try { ws.terminate(); } catch { /* already closed */ }
            err ? reject(err) : resolve(lines);
        };
        const bump = () => { clearTimeout(settle); settle = setTimeout(() => finish(), CONSOLE_SETTLE_MS); };
        hard = setTimeout(() => finish(), CONSOLE_MAX_MS);
        ws.on('open', () => ws.send(JSON.stringify({ event: 'auth', args: [token] })));
        ws.on('message', (raw) => {
            let msg;
            try { msg = JSON.parse(raw.toString()); } catch { return; }
            if (msg.event === 'auth success') {
                ws.send(JSON.stringify({ event: 'send logs', args: [] }));
                bump();
            } else if (msg.event === 'console output' || msg.event === 'daemon output') {
                const line = Array.isArray(msg.args) ? msg.args[0] : null;
                if (typeof line === 'string') lines.push(line);
                bump();
            } else if (msg.event === 'jwt error' || msg.event === 'token expired') {
                finish(new Error('auth failed'));
            }
        });
        ws.on('error', (e) => finish(e));
        ws.on('close', () => finish());
    });
}

/**
 * POST /console — count players from the server console for games with no usable
 * wire query (e.g. crossplay Valheim). The latest line matching `count` carries
 * the absolute count; `name` (optional) extracts player names. Both are plain
 * JS regex source strings applied with `flags`.
 */
async function handleConsole(req, res) {
    if (!authorized(req)) return send(res, 401, { ok: false, error: 'unauthorized' });

    let payload;
    try {
        payload = JSON.parse((await readBody(req)) || '{}');
    } catch {
        return send(res, 400, { ok: false, error: 'invalid json' });
    }

    const { socket, token, count, name, origin } = payload;
    const flags = typeof payload.flags === 'string' ? payload.flags : '';
    const maxNames = Number.isInteger(payload.maxNames) ? payload.maxNames : MAX_NAMES;
    if (!socket || !token || !count) {
        return send(res, 400, { ok: false, error: 'socket, token and count are required' });
    }

    let countRe, nameRe;
    try {
        countRe = new RegExp(count, flags);
        nameRe = name ? new RegExp(name, flags) : null;
    } catch {
        return send(res, 400, { ok: false, error: 'invalid regex' });
    }

    let lines;
    try {
        lines = await readConsole(socket, token, origin);
    } catch (err) {
        return send(res, 200, { ok: false, error: String(err?.message ?? err) });
    }

    // Absolute count: the LAST matching line wins (most recent state).
    let online = null;
    for (const line of lines) {
        const m = line.match(countRe);
        if (m && m[1] != null) online = Number(m[1]);
    }
    if (online === null) {
        return send(res, 200, { ok: false, error: 'no count line in console backlog' });
    }

    // Names are best-effort: keep the most recent distinct matches, capped.
    let players = [];
    if (nameRe && online > 0) {
        const seen = [];
        for (const line of lines) {
            const m = line.match(nameRe);
            if (m && m[1]) seen.push(m[1].trim());
        }
        players = [...new Set(seen.reverse())].slice(0, Math.min(maxNames, online)).reverse();
    }

    return send(res, 200, { ok: true, online, max: null, name: null, players });
}

const server = createServer((req, res) => {
    if (req.method === 'GET' && req.url === '/healthz') {
        return send(res, 200, { ok: true });
    }
    if (req.method === 'POST' && (req.url === '/query' || req.url?.startsWith('/query?'))) {
        return void handleQuery(req, res);
    }
    if (req.method === 'POST' && (req.url === '/console' || req.url?.startsWith('/console?'))) {
        return void handleConsole(req, res);
    }
    return send(res, 404, { ok: false, error: 'not found' });
});

server.listen(PORT, HOST, () => {
    // eslint-disable-next-line no-console
    console.log(`[peregrine-game-query] listening on http://${HOST}:${PORT}`);
});
