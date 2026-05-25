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
// Run:  cd plugins/peregrine-player-counter/sidecar && npm install && node index.mjs
// Env:  GAME_QUERY_PORT (9899), GAME_QUERY_HOST (127.0.0.1), GAME_QUERY_TOKEN,
//       GAME_QUERY_TIMEOUT_MS (5000), GAME_QUERY_EOS_TIMEOUT_MS (6000),
//       GAME_QUERY_MAX_NAMES (5), GAME_QUERY_MAX_BODY (4096)

import { createServer } from 'node:http';
import dns from 'node:dns';
import { GameDig } from 'gamedig';

const PORT = Number(process.env.GAME_QUERY_PORT ?? 9899);
const HOST = process.env.GAME_QUERY_HOST ?? '127.0.0.1';
const TOKEN = process.env.GAME_QUERY_TOKEN ?? '';
const TIMEOUT_MS = Number(process.env.GAME_QUERY_TIMEOUT_MS ?? 5000);
const EOS_TIMEOUT_MS = Number(process.env.GAME_QUERY_EOS_TIMEOUT_MS ?? 6000);
const MAX_NAMES = Number(process.env.GAME_QUERY_MAX_NAMES ?? 5);
const MAX_BODY = Number(process.env.GAME_QUERY_MAX_BODY ?? 4096);

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
    const timeoutMs = isEos ? Math.max(TIMEOUT_MS, EOS_TIMEOUT_MS) : TIMEOUT_MS;

    try {
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

const server = createServer((req, res) => {
    if (req.method === 'GET' && req.url === '/healthz') {
        return send(res, 200, { ok: true });
    }
    if (req.method === 'POST' && (req.url === '/query' || req.url?.startsWith('/query?'))) {
        return void handleQuery(req, res);
    }
    return send(res, 404, { ok: false, error: 'not found' });
});

server.listen(PORT, HOST, () => {
    // eslint-disable-next-line no-console
    console.log(`[peregrine-game-query] listening on http://${HOST}:${PORT}`);
});
