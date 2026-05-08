import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { getCsrfHeaders } from './http';

/**
 * Lazy-instantiated Laravel Echo singleton tied to the panel's Reverb
 * server. Reads its config from the `<meta name="reverb-*">` tags rendered
 * by the Laravel layout (see `resources/views/app.blade.php`) :
 *   <meta name="reverb-key" content="…">
 *   <meta name="reverb-host" content="…">
 *   <meta name="reverb-port" content="443">
 *   <meta name="reverb-scheme" content="https">
 *
 * When the meta tags are absent or empty (e.g. an install where the admin
 * never set up Reverb), `getEcho()` returns `null` and every consumer must
 * degrade gracefully — the SPA falls back to its existing query staleTime
 * (5 minutes) without crashing.
 *
 * Authentication for private channels rides on the existing Laravel session
 * cookie + CSRF header (same shape as `services/http.ts`). No bearer
 * tokens, no api-key gymnastics.
 */

declare global {
    interface Window {
        Pusher?: typeof Pusher;
        Echo?: Echo<'reverb'>;
    }
}

interface ReverbConfig {
    key: string;
    host: string;
    port: number;
    scheme: 'http' | 'https';
}

let cached: Echo<'reverb'> | null = null;
let initialised = false;

export function getEcho(): Echo<'reverb'> | null {
    if (initialised) {
        return cached;
    }
    initialised = true;

    const config = readConfig();
    if (config === null) {
        return null;
    }

    // Pusher is the underlying transport ; Reverb speaks the Pusher protocol.
    window.Pusher = Pusher;

    cached = new Echo({
        broadcaster: 'reverb',
        key: config.key,
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        // Reverb listens on /app/{key} on the same vhost — nginx upgrades
        // the connection to a WebSocket and proxies to localhost:6001.
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                ...getCsrfHeaders(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    });

    window.Echo = cached;

    return cached;
}

/**
 * Force a fresh handshake — useful when the user logs in/out or the CSRF
 * token rotated. Drops the cached instance, the next `getEcho()` rebuilds.
 */
export function resetEcho(): void {
    if (cached !== null) {
        try {
            cached.disconnect();
        } catch {
            // disconnect on a half-broken socket throws — ignore.
        }
    }
    cached = null;
    initialised = false;
    delete window.Echo;
}

function readConfig(): ReverbConfig | null {
    const key = readMeta('reverb-key');
    if (key === '') {
        return null;
    }

    let host = readMeta('reverb-host');
    // Loopback-host guard. If the meta says "localhost" / "127.0.0.1" but
    // the page itself was loaded from a non-loopback origin (typical when
    // accessing the panel via a LAN IP, ngrok tunnel, or staging domain
    // while REVERB_HOST in .env still points at "localhost"), we'd dial
    // the BROWSER's loopback instead of the server's — and every channel
    // subscription would silently fail with no console error. Override
    // with `window.location.hostname` so the WS lands on the same host
    // that just served us the HTML. The Blade template already does the
    // server-side equivalent fallback to `request()->getHost()`; this is
    // the belt-and-suspenders for cached HTML / stale env values.
    const pageHost = window.location.hostname;
    const hostIsLoopback = host === '' || host === 'localhost' || host === '127.0.0.1';
    const pageIsLoopback = pageHost === 'localhost' || pageHost === '127.0.0.1';
    if (hostIsLoopback && !pageIsLoopback) {
        host = pageHost;
    }
    if (host === '') {
        return null;
    }

    const portRaw = readMeta('reverb-port');
    const port = Number.parseInt(portRaw, 10);
    if (!Number.isFinite(port) || port <= 0) {
        return null;
    }

    // Mixed-content guard. A page served over HTTPS cannot open a plain
    // ws:// — the browser blocks it before the handshake even starts.
    // Coerce the scheme up to wss:// when the page is HTTPS so the panel
    // doesn't silently lose live updates after an HTTPS deploy without a
    // matching REVERB_SCHEME flip.
    let schemeRaw = readMeta('reverb-scheme').toLowerCase();
    if (window.location.protocol === 'https:' && schemeRaw === 'http') {
        schemeRaw = 'https';
    }
    const scheme: 'http' | 'https' = schemeRaw === 'http' ? 'http' : 'https';

    return { key, host, port, scheme };
}

function readMeta(name: string): string {
    const el = document.querySelector(`meta[name="${name}"]`);
    return el?.getAttribute('content')?.trim() ?? '';
}
