/**
 * Reads the XSRF-TOKEN cookie set by Sanctum / Laravel's session middleware.
 * The cookie is URL-encoded by Laravel — decode before sending it back as
 * `X-XSRF-TOKEN`. Always more current than the meta tag (which is frozen at
 * page render and goes stale after every session regeneration, e.g. login).
 */
function getXsrfFromCookie(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    if (!match || match[1] === undefined) return '';
    try {
        return decodeURIComponent(match[1]);
    } catch {
        return '';
    }
}

function getCsrfFromMeta(): string {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta?.getAttribute('content') ?? '';
}

/**
 * Returns headers carrying a valid CSRF proof for Laravel's VerifyCsrfToken
 * middleware. Picks **exactly one** of:
 *   - `X-XSRF-TOKEN` (the encrypted XSRF-TOKEN cookie value, URL-decoded —
 *     Laravel decrypts it server-side)
 *   - `X-CSRF-TOKEN` (the raw token rendered in `<meta name="csrf-token">`)
 *
 * Never both. Laravel checks X-CSRF-TOKEN first and treats it as a raw
 * session token; if you populate it with the encrypted cookie value
 * Laravel rejects with 419 without trying the X-XSRF-TOKEN fallback.
 *
 * The cookie is preferred because it stays in sync with Laravel's session
 * (re-set on every Sanctum response). The meta tag is frozen at first
 * paint and goes stale after any session rotation (login, 2FA, install).
 */
export function getCsrfHeaders(): Record<string, string> {
    const cookieToken = getXsrfFromCookie();
    if (cookieToken) return { 'X-XSRF-TOKEN': cookieToken };
    const metaToken = getCsrfFromMeta();
    if (metaToken) return { 'X-CSRF-TOKEN': metaToken };
    return {};
}

/**
 * @deprecated Use `getCsrfHeaders()` — the raw return value can't safely be
 * placed in either header without knowing which header Laravel will read.
 */
export function getCsrfToken(): string {
    return getCsrfFromMeta() || getXsrfFromCookie();
}

let csrfRefreshInFlight: Promise<void> | null = null;

/**
 * Pings Sanctum's CSRF endpoint to refresh the XSRF-TOKEN cookie. Used as
 * a one-shot recovery when a request comes back with 419 — typically right
 * after login when the SPA still holds the pre-login token.
 */
async function refreshCsrfCookie(): Promise<void> {
    if (csrfRefreshInFlight) return csrfRefreshInFlight;
    csrfRefreshInFlight = fetch('/sanctum/csrf-cookie', {
        credentials: 'same-origin',
    }).then(() => undefined).finally(() => {
        csrfRefreshInFlight = null;
    });
    return csrfRefreshInFlight;
}

export class ApiError extends Error {
    constructor(
        public status: number,
        public data: Record<string, unknown>,
    ) {
        super(`API Error: ${status}`);
    }
}

function buildHeaders(extra: HeadersInit | undefined, jsonBody: boolean): Headers {
    const headers = new Headers(extra ?? {});
    if (jsonBody && !headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/json');
    }
    if (!headers.has('Accept')) {
        headers.set('Accept', 'application/json');
    }
    for (const [name, value] of Object.entries(getCsrfHeaders())) {
        headers.set(name, value);
    }
    return headers;
}

async function fetchOnce(url: string, options: RequestInit, jsonBody: boolean): Promise<Response> {
    return fetch(url, {
        ...options,
        credentials: 'same-origin',
        headers: buildHeaders(options.headers, jsonBody),
    });
}

async function fetchWithCsrfRetry(
    url: string,
    options: RequestInit,
    jsonBody: boolean,
): Promise<Response> {
    let response = await fetchOnce(url, options, jsonBody);
    if (response.status === 419) {
        await refreshCsrfCookie();
        response = await fetchOnce(url, options, jsonBody);
    }
    return response;
}

export async function request<T>(url: string, options: RequestInit = {}): Promise<T> {
    const response = await fetchWithCsrfRetry(url, options, true);

    if (!response.ok) {
        const data = await response.json().catch(() => ({})) as Record<string, unknown>;
        maybeRedirectOn2faEnforcement(response.status, data);
        throw new ApiError(response.status, data);
    }

    return response.json() as Promise<T>;
}

export async function requestRaw(url: string, options: RequestInit = {}): Promise<Response> {
    const response = await fetchWithCsrfRetry(url, options, false);

    if (!response.ok) {
        const data = await response.json().catch(() => ({})) as Record<string, unknown>;
        maybeRedirectOn2faEnforcement(response.status, data);
        throw new ApiError(response.status, data);
    }

    return response;
}

/**
 * RequireTwoFactor middleware returns 403 with
 *   { error: 'auth.2fa.required_admin_setup', setup_url: '/2fa/setup?enforced=1' }
 * when an admin hits a gated route without 2FA configured. Hijack the response
 * — redirect the browser before the caller throws. Keeps admin-gated pages
 * from having to implement the 403 handling manually.
 *
 * Skips the redirect when we're already on the setup page (prevents a loop
 * if the setup page itself makes an API call).
 */
function maybeRedirectOn2faEnforcement(status: number, data: Record<string, unknown>): void {
    if (status !== 403) return;
    if (data['error'] !== 'auth.2fa.required_admin_setup') return;
    const target = data['setup_url'];
    if (typeof target !== 'string') return;
    if (window.location.pathname.startsWith('/2fa/setup')) return;
    window.location.href = target;
}
