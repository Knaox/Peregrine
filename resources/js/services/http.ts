/**
 * Reads the XSRF-TOKEN cookie set by Sanctum / Laravel's session middleware.
 * The cookie is URL-encoded by Laravel — decode before sending it back as
 * `X-XSRF-TOKEN`. Always more current than the meta tag (which is frozen at
 * page render and goes stale after every session regeneration, e.g. login).
 */
function getXsrfFromCookie(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    if (!match) return '';
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
 * Best-effort CSRF token: prefer the live cookie value, fall back to the
 * meta tag rendered into the initial HTML. The meta tag matches the very
 * first render's session, but loses sync as soon as Laravel rotates the
 * session — most commonly on login. The cookie always reflects the current
 * session because Sanctum's stateful middleware re-sets it on every reply.
 */
export function getCsrfToken(): string {
    return getXsrfFromCookie() || getCsrfFromMeta();
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
    const token = getCsrfToken();
    if (token) {
        headers.set('X-XSRF-TOKEN', token);
        headers.set('X-CSRF-TOKEN', token);
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
